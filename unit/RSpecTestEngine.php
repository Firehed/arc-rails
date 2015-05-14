<?php

class RSpecTestEngine extends ArcanistUnitTestEngine {

  private $affectedTests;
  private $projectRoot;

  protected function supportsRunAllTests() {
    return true;
  }

  public function run() {
    $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
    // If called with --everything, grab all tests in /spec/
    if ($this->getRunAllTests()) {
      $files = (new FileFinder($this->projectRoot.'/spec/'))
        ->withType('f')
        ->withSuffix('rb')
        ->find();
      foreach ($files as $file) {
        if ('_spec.rb' == substr($file, -8)) {
          $tests[] = 'spec/'.$file;
        }
      }
      $this->setPaths($tests);
    }

    foreach ($this->getPaths() as $path) {
      $path = Filesystem::resolvePath($path, $this->projectRoot);
      if (is_dir($path)) {
        continue;
      }
      if (substr($path, -3) != '.rb') {
        continue;
      }
      if (substr($path, -8) == '_spec.rb') {
        $this->affectedTests[$path] = $path;
        continue;
      }
      if ($test = $this->findTestForFile($path)) {
        $this->affectedTests[$path] = $test;
      }
    }

    if (!$this->affectedTests) {
      throw new ArcanistNoEffectException("No tests to run.");
    }

    $futures = [];
    $tempfiles = [];
    foreach ($this->affectedTests as $class_path => $test_path) {
      $json_tmp = new TempFile();
      $tempfiles[$test_path] = [
        'json' => $json_tmp,
      ];
      $futures[$test_path] = new ExecFuture('%C %C %C -o %s %s',
        'rspec',
        '-f json',
        '--failure-exit-code 2',
        $json_tmp,
        $test_path);
    }

   $results = [];
    $cwd = getcwd();
    chdir($this->projectRoot);
    foreach ((new FutureIterator($futures))->limit(4) as $test => $future) {
      list($err, $stdout, $stderr) = $future->resolve();
      $json = file_get_contents($tempfiles[$test]['json']);
      $results[] = $this->parseTestResults($test, $json, $stderr, $err);
    }
    chdir($cwd);
    return array_mergev($results);
  }

  private function findTestForFile($file_path) {
    // Follow the hard rails convention: replace app/path/to/class.rb with
    // spec/path/to/class_spec.rb
    $relative_path = substr($file_path, strlen($this->projectRoot)+1);
    $accpetable_directories = ['app/', 'lib/'];
    $found = false;
    foreach ($accpetable_directories as $dir) {
      if (0 === strpos($relative_path, $dir)) {
        $found = true;
      }
    }
    if (!$found) {
      return false;
    }

    $expected_loc = 'spec/'.substr($relative_path, 4, -3).'_spec.rb';
    $expected_loc = Filesystem::resolvePath($expected_loc, $this->projectRoot);
    if (Filesystem::pathExists($expected_loc)) {
      return $expected_loc;
    }
    return false;
  }

  private function parseTestResults($file, $json, $stderr, $return_code) {
    // We configure rspec to return 2 if it's a test failure. 1 is the test
    // being broken.
    if ($return_code == 1) {
      return [$this->generateBadTestResult()
        ->setName($file)
        ->setUserData($stderr)
      ];
    }
    $results = [];
    foreach (json_decode($json)->examples as $test_result) {
      $userData = '';
      if ($test_result->status == 'failed') {
        $userData = sprintf("%s\n%s",
          $test_result->exception->class,
          $test_result->exception->message);
        if (0 === strpos($test_result->exception->backtrace[0], $file)) {
          $results[] = $this->generateBadTestResult()
            ->setName($file)
            ->setUserData($userData);
          continue;
        }
      }

      $result = (new ArcanistUnitTestResult())
        ->setDuration($test_result->run_time)
        ->setResult($this->getUnitTestResultFromString($test_result->status))
        ->setUserData(trim($userData))
        ->setName(trim($test_result->full_description));
      $results[] = $result;
    }
    return $results;

    $result->setName($name);
    $result->setUserData($user_data);
    return [$result];
  }

  private function getUnitTestResultFromString($string) {
    switch ($string) {
      case 'passed':
        return ArcanistUnitTestResult::RESULT_PASS;
      case 'failed':
        return ArcanistUnitTestResult::RESULT_FAIL;
      case 'pending':
        return ArcanistUnitTestResult::RESULT_SKIP;
//        return ArcanistUnitTestResult::RESULT_POSTPONED;
      default:
        echo "Unknown -- $string --\n";
        break;
        //RESULT_SKIP => '<
        //RESULT_BROKEN =>
        //RESULT_UNSOUND =>
    }
  }

  private function generateBadTestResult() {
    return (new ArcanistUnitTestResult())
      ->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
  }

}
