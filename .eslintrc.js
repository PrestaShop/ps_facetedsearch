module.exports = {
  root: true,
  env: {
    browser: true,
    node: true,
    es6: true,
    jquery: true,
  },
  parserOptions: {
    parser: 'babel-eslint',
  },
  plugins: [
    'import',
  ],
  extends: [
    'prestashop',
  ],
  globals: {
    PS_LAYERED_INDEXED: true,
    filters: true,
    prestashop: true,
    translations: true,
  },
};
