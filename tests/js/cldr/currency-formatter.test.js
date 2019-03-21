import {expect} from 'chai';
import CurrencyFormatter from '../../../_dev/cldr/currency-formatter';
import PriceSpecification from '../../../_dev/cldr/price-specification';
import NumberSymbol from '../../../_dev/cldr/number-symbol';

describe('CurrencyFormatter', () => {
  let currency;
  beforeEach(() => {
    const symbol = new NumberSymbol(
      '.',
      ',',
      ';',
      '%',
      '-',
      '+',
      'E',
      '×',
      '‰',
      '∞',
      'NaN',
    );
    currency = new CurrencyFormatter();
    currency.numberSpecification = new PriceSpecification(
      '#,##0.###',
      '-#,##0.###',
      symbol,
      3,
      0,
      true,
      3,
      3,
    );
  });

  describe('extractMajorMinorDigits', () => {
    const assertions = [
      [10, ['10', '']],
      [10.1, ['10', '1']],
      [11.12345, ['11', '12345']],
      [11.00000, ['11', '']],
    ];
    assertions.forEach((assertion) => {
      it(`test ${assertion[0]}`, () => {
        expect(currency.extractMajorMinorDigits(assertion[0])).to.eql(assertion[1]);
      });
    });
  });

  describe('getCldrPattern', () => {
    const assertions = [
      [false, '#,##0.###'],
      [true, '-#,##0.###'],
    ];
    assertions.forEach((assertion) => {
      it(`test isNegative ${assertion[0]}`, () => {
        expect(currency.getCldrPattern(assertion[0])).to.eq(assertion[1]);
      });
    });
  });

  describe('splitMajorGroups', () => {
    const assertions = [
      ['10', '10'],
      ['100', '100'],
      ['1000', '1,000'],
      ['10000', '10,000'],
      ['100000', '100,000'],
      ['1000000', '1,000,000'],
      ['10000000', '10,000,000'],
      ['100000000', '100,000,000'],
    ];
    assertions.forEach((assertion) => {
      it(`test ${assertion[0]}`, () => {
        expect(currency.splitMajorGroups(assertion[0])).to.eq(assertion[1]);
      });
    });
  });

  describe('adjustMinorDigitsZeroes', () => {
    const assertions = [
      ['10000', '10'],
      ['100', '100'],
      ['12', '12'],
      ['120', '120'],
      ['1271', '1271'],
      ['1270', '127'],
    ];
    assertions.forEach((assertion) => {
      it(`test ${assertion[0]}`, () => {
        currency.numberSpecification.minFractionDigits = 2;
        expect(currency.adjustMinorDigitsZeroes(assertion[0])).to.eq(assertion[1]);
      });
    });
  });

  describe('addPlaceholders', () => {
    const assertions = [
      ['100,000.13', '¤#,##0.00', '¤100,000.13'],
      ['100.13', '¤#,##0.00', '¤100.13'],
    ];
    assertions.forEach((assertion) => {
      it(`test ${assertion[0]} with pattern ${assertion[1]}`, () => {
        expect(currency.addPlaceholders(assertion[0], assertion[1])).to.eq(assertion[2]);
      });
    });
  });

  describe('replaceSymbols', () => {
    it('should replace all symbols', () => {
      currency.numberSpecification.symbol = new NumberSymbol(
        '-_-',
        ':)',
        ';',
        '%',
        '-',
        '+',
        'E',
        '×',
        '‰',
        '∞',
        'NaN',
      );
      expect(currency.replaceSymbols('¤10,000,000.13')).to.eq('¤10:)000:)000-_-13');
    });
  });
});
