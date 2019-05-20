import {expect} from 'chai';
import getQueryParameters from '../../../_dev/front/urlparser';

describe('getQueryParameters', () => {
  const assertions = [
    [
      'q=%C3%89tat-Nouveau%2FPrix-%E2%82%AC-22-42',
      [{
        name: 'q',
        value: 'État-Nouveau/Prix-€-22-42',
      }],
    ],
    [
      'q=Prix-%E2%82%AC-22-42/Composition-Carton recycl%C3%A9',
      [{
        name: 'q',
        value: 'Prix-€-22-42/Composition-Carton recyclé',
      }],
    ],
    [
      'q=Prix-%E2%82%AC-22-42/Composition-Carton recycl%C3%A9&something=thisIsSparta',
      [
        {
          name: 'q',
          value: 'Prix-€-22-42/Composition-Carton recyclé',
        },
        {
          name: 'something',
          value: 'thisIsSparta',
        },
      ],
    ],
  ];
  assertions.forEach((assertion) => {
    it(`test ${assertion[0]}`, () => {
      expect(getQueryParameters(assertion[0])).to.eql(assertion[1]);
    });
  });
});
