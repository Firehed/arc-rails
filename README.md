# arc-rails

A bit of tooling to make `arc unit` work in a Rails project.

## Installation

Currently, `git clone` the repo into your Rails project, and include the
following in your `.arcconfig`:

```
"load": ["path/to/this/directory"],
"unit.engine": "RSpecTestEngine"
```

Currently, this cannot be installed as a gem. This is due to a limitation in
Arcanist, where load paths will not expand environment variables. If that is
fixed, then this can be converted to a gem.

## Configuration

At this time, no configuration is suported. The library follows Rails
conventions as closely as possible, so code written The Rails Way(TM) should
work out of the box.

## Requirements
This library makes assumptions about naming conventions that are in line with
Rails naming conventions (as of ~4.2):

* The code to test should be under `ROOT/app` or `ROOT/lib`
* Tests should be in `ROOT/spec`
* Tests files should end in `_spec.rb`
* There should be a 1:1 mapping between names and directories. For example,
  source code located at `ROOT/app/models/user.rb` should have its
  corresponding test at `ROOT/spec/models/user_spec.rb`

Each test file is run in an isolated process per Aranist conventions, so if
there are dependencies between test files, you will likely encounter problems.

## Usage
`arc unit` should work as expected. The `--everything` flag is supported.

Code coverage reporting is not supported at this time.
