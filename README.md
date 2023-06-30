# Faceted search module

[![Build Status](https://travis-ci.com/PrestaShop/ps_facetedsearch.svg?branch=master)](https://travis-ci.com/PrestaShop/ps_facetedsearch)
[![Latest Stable Version](https://poser.pugx.org/PrestaShop/ps_facetedsearch/v)](//packagist.org/packages/PrestaShop/ps_facetedsearch)
[![Total Downloads](https://poser.pugx.org/PrestaShop/ps_facetedsearch/downloads)](//packagist.org/packages/PrestaShop/ps_facetedsearch)
[![GitHub license](https://img.shields.io/github/license/PrestaShop/ps_facetedsearch)](https://github.com/PrestaShop/ps_facetedsearch/LICENSE.md)


## About

Filter your catalog to help visitors picture the category tree and browse your store easily.

## About this fork

This fork adds JS Slider for Features and Attribute Groups.
It is experimental version. Stable for ordered numeric values. Untested for anything else.

### Configuration

1. In PrestaShop Back Office go to _Modules > Module Manager > Modules (tab) > Faceted search > Configure (button) > Filters templates (table) > Edit (button)_ of desired Template.
2. Select "Slider (experimental)" as Filter style of desired Feature or Attribute group.

If you wish to show units within a Slider, then create new key `PS_FACETEDSEARCH_SLIDER_FEATURE_<feature id>_UNIT` in PrestaShop Config table (in Database).

Note: Order of values in the slider are taken from the Feature / Attribute group.

## Multistore compatibility

This module is partially compatible with the multistore feature. Some of its options might not be available.

## Reporting issues

You can report issues with this module in the main PrestaShop repository. [Click here to report an issue][report-issue]. 

## Requirements

Required only for development:

- npm
- composer

## Installation

Install all dependencies. Be careful, you need NodeJs 14+.
```
npm install
composer install
```

## Usage

```
npm run dev # Watch js/css files for changes
npm run build # Build for production
```

## Contributing

PrestaShop modules are open source extensions to the [PrestaShop e-commerce platform][prestashop]. Everyone is welcome and even encouraged to contribute with their own improvements!

Just make sure to follow our [contribution guidelines][contribution-guidelines].

## License

This module is released under the [Academic Free License 3.0][AFL-3.0] 

[report-issue]: https://github.com/PrestaShop/PrestaShop/issues/new/choose
[prestashop]: https://www.prestashop.com/
[contribution-guidelines]: https://devdocs.prestashop.com/1.7/contribute/contribution-guidelines/project-modules/
[AFL-3.0]: https://opensource.org/licenses/AFL-3.0
