/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
import js from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import globals from 'globals';

export default [
  {
    ignores: [
      'views/**',
      'node_modules/**',
      'vendor/**',
    ],
  },
  js.configs.recommended,
  {
    plugins: {
      '@stylistic': stylistic,
    },
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.jquery,
        ...globals.mocha,
        PS_LAYERED_INDEXED: 'readonly',
        filters: 'readonly',
        prestashop: 'readonly',
        translations: 'readonly',
        Sortable: 'readonly',
      },
    },
    rules: {
      'no-unused-vars': ['error', {args: 'none'}],

      // Formatting rules, kept aligned with the style the module was written in.
      '@stylistic/arrow-parens': ['error', 'always'],
      '@stylistic/comma-dangle': ['error', 'always-multiline'],
      '@stylistic/comma-spacing': 'error',
      '@stylistic/eol-last': 'error',
      '@stylistic/indent': ['error', 2, {SwitchCase: 1}],
      '@stylistic/key-spacing': 'error',
      '@stylistic/max-len': ['error', {code: 120, ignoreUrls: true}],
      '@stylistic/no-multiple-empty-lines': ['error', {max: 1, maxEOF: 0}],
      '@stylistic/no-trailing-spaces': 'error',
      '@stylistic/object-curly-spacing': ['error', 'never'],
      '@stylistic/quotes': ['error', 'single', {allowTemplateLiterals: 'never'}],
      '@stylistic/semi': 'error',
      '@stylistic/space-before-blocks': 'error',
      '@stylistic/space-before-function-paren': ['error', {
        anonymous: 'always',
        named: 'never',
        asyncArrow: 'always',
      }],
      '@stylistic/space-infix-ops': 'error',
    },
  },
];
