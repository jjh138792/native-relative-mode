# Native Relative Mode

WordPress MU plugin that forces frontend URLs to be relative,
while keeping RSS, Sitemap and REST absolute.

## Why

WordPress always outputs absolute URLs.
This plugin converts them to relative paths on normal frontend requests.

## Features

- Converts links, assets, and content URLs to relative paths
- Keeps RSS feed absolute
- Keeps Sitemap absolute
- Keeps REST API absolute
- Works as MU plugin

## Installation

1. Put the file into:

wp-content/mu-plugins/native-relative-mode.php

2. Done.

## Notes

Tested on WordPress 6.x.
