const path = require('path');

module.exports = {
  entry: {
    front: './js/front',
    back: './js/back',
  },
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, './views/js')
  },
  module: {
    rules: [
      {
        test: /\.jsx$/,
        exclude: /node_modules/,
        use: ['babel-loader'],
      },
    ],
  },
  stats: {
    colors: true
  },
  devtool: 'source-map',
};
