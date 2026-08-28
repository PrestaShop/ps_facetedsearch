# Faceted search module

[![Build Status](https://travis-ci.com/PrestaShop/ps_facetedsearch.svg?branch=master)](https://travis-ci.com/PrestaShop/ps_facetedsearch)
[![Latest Stable Version](https://poser.pugx.org/PrestaShop/ps_facetedsearch/v)](//packagist.org/packages/PrestaShop/ps_facetedsearch)
[![Total Downloads](https://poser.pugx.org/PrestaShop/ps_facetedsearch/downloads)](//packagist.org/packages/PrestaShop/ps_facetedsearch)
[![GitHub license](https://img.shields.io/github/license/PrestaShop/ps_facetedsearch)](https://github.com/PrestaShop/ps_facetedsearch/LICENSE.md)


## About

Filter your catalog to help visitors picture the category tree and browse your store easily.

## Compatibility

PrestaShop: 1.7.6.0 or later

## Multistore compatibility

This module is partially compatible with the multistore feature. Some of its options might not be available.

## Reporting issues

You can report issues with this module in the main PrestaShop repository. [Click here to report an issue][report-issue]. 

## Requirements

Required only for development:

- npm
- composer

## Installation

Install all dependencies. Be careful, you need NodeJS 20.19+.
```
npm ci
composer install
```

## Usage

The front and back office assets are built from `_dev/` with [esbuild][esbuild] (JavaScript)
and [Dart Sass][dart-sass] (stylesheets).

```
npm run dev   # Watch js/scss files for changes
npm run build # Build for production
npm run lint  # Check the coding style
npm run test  # Run the JavaScript unit tests
```

The result is written to `views/dist/`, which is committed to the repository and shipped
as-is in the release archive: **always run `npm run build` and commit `views/dist/` along
with your changes to `_dev/`**. The CI verifies that both stay in sync.

## Contributing

PrestaShop modules are open source extensions to the [PrestaShop e-commerce platform][prestashop]. Everyone is welcome and even encouraged to contribute with their own improvements!

Just make sure to follow our [contribution guidelines][contribution-guidelines].

## License

This module is released under the [Academic Free License 3.0][AFL-3.0] 

[report-issue]: https://github.com/PrestaShop/PrestaShop/issues/new/choose
[prestashop]: https://www.prestashop.com/
[contribution-guidelines]: https://devdocs.prestashop.com/1.7/contribute/contribution-guidelines/project-modules/
[AFL-3.0]: https://opensource.org/licenses/AFL-3.0
[esbuild]: https://esbuild.github.io/
[dart-sass]: https://sass-lang.com/dart-sass/
