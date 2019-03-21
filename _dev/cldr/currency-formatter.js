
class CurrencyFormatter {
  /**
   * These placeholders are used in CLDR number formatting templates.
   * They are meant to be replaced by the correct localized symbols in the number formatting process.
   */
  static CURRENCY_SYMBOL_PLACEHOLDER = '¤';
  static DECIMAL_SEPARATOR_PLACEHOLDER = '.';
  static GROUP_SEPARATOR_PLACEHOLDER = ',';
  static MINUS_SIGN_PLACEHOLDER = '-';
  static PERCENT_SYMBOL_PLACEHOLDER = '%';
  static PLUS_SIGN_PLACEHOLDER = '+';



  /**
   * @var string The wanted rounding mode when formatting numbers.
   *             Cf. PrestaShop\Decimal\Operation\Rounding::ROUND_* values
   */
  static roundingMode;

  /**
   * @var string Numbering system to use when formatting numbers
   *
   * @see http://cldr.unicode.org/translation/numbering-systems
   */
  static numberingSystem;

  /**
   * Number specification to be used when formatting a number.
   *
   * @var NumberSpecification
   */
  static numberSpecification;


  /**
   * Create a number formatter instance.
   *
   * @param int roundingMode The wanted rounding mode when formatting numbers
   *                          Cf. PrestaShop\Decimal\Operation\Rounding::ROUND_* values
   * @param string numberingSystem Numbering system to use when formatting numbers. @see http://cldr.unicode.org/translation/numbering-systems
   */
  constructor(roudingMode, numberingSystem) {
    this.roundingMode = roundingMode;
    this.numberingSystem = numberingSystem;
  }

  /**
   * Formats the passed number according to specifications.
   *
   * @param int|float|string number The number to format
   * @param NumberSpecification specification Number specification to be used (can be a number spec, a price spec, a percentage spec)
   *
   * @return string The formatted number
   *                You should use this this value for display, without modifying it
   *
   */
  format(number, pattern, symbol) {
    this.numberSpecification = specification;

    /*
     * We need to work on the absolute value first.
     * Then the CLDR pattern will add the sign if relevant (at the end).
     */
    let [majorDigits, minorDigits] = this.extractMajorMinorDigits(abs(number));
    majorDigits = this.splitMajorGroups(majorDigits);
    minorDigits = this.adjustMinorDigitsZeroes(minorDigits);

    // Assemble the final number
    let formattedNumber = majorDigits;
    if (minorDigits) {
      formattedNumber += this.DECIMAL_SEPARATOR_PLACEHOLDER . minorDigits;
    }

    // Get the good CLDR formatting pattern. Sign is important here !
    formattedNumber = this.addPlaceholders(formattedNumber, pattern);
    formattedNumber = this.localizeNumber(formattedNumber);

    formattedNumber = this.performSpecificReplacements(formattedNumber, symbol);

    return formattedNumber;
  }

  /**
   * Get number's major and minor digits.
   *
   * Major digits are the "integer" part (before decimal separator), minor digits are the fractional part
   * Result will be an array of exactly 2 items: [majorDigits, minorDigits]
   *
   * Usage example:
   *  list(majorDigits, minorDigits) = this.getMajorMinorDigits(decimalNumber);
   *
   * @param DecimalNumber number
   *
   * @return string[]
   */
  extractMajorMinorDigits(number) {
    // Get the number's major and minor digits.
    let majorDigits = Math.floor(number);
    minorDigits = number % 1;
    minorDigits = (0 === minorDigits) ? '' : minorDigits;

    return [majorDigits, minorDigits];
  }

  /**
   * Splits major digits into groups.
   *
   * e.g.: Given the major digits "1234567", and major group size
   *  configured to 3 digits, the result would be "1 234 567"
   *
   * @param majorDigits
   *  The major digits to be grouped
   *
   * @return string
   *                The grouped major digits
   */
  splitMajorGroups(majorDigits) {
    // Reverse the major digits, since they are grouped from the right.
    majorDigits = majorDigits.split().reverse();
    // Group the major digits.
    const groups = [];
    groups.push(majorDigits.splice(0, this.numberSpecification.getPrimaryGroupSize()));
    while (!majorDigits) {
      groups.push(majorDigits.splice(0, this.numberSpecification.getSecondaryGroupSize()));
    }
    // Reverse back the digits and the groups
    groups = groups.reverse();
    const newGroups = [];
    groups.forEach((group) => {
      newGroups.push(''.join(group.reverse()));
    });

    // Reconstruct the major digits.
    majorDigits = this.GROUP_SEPARATOR_PLACEHOLDER.join(groups);

    return majorDigits;
  }

  /**
   * Adds or remove trailing zeroes, depending on specified min and max fraction digits numbers.
   *
   * @param string minorDigits
   *                            Digits to be adjusted with (trimmed or padded) zeroes
   *
   * @return string
   *                The adjusted minor digits
   */
  adjustMinorDigitsZeroes(minorDigits) {
    if (strlen(minorDigits) > this.numberSpecification.getMaxFractionDigits()) {
      // Strip any trailing zeroes.
      minorDigits = rtrim(minorDigits, '0');
    }

    if (strlen(minorDigits) < this.numberSpecification.getMinFractionDigits()) {
      // Re-add needed zeroes
      minorDigits = str_pad(
        minorDigits,
        this.numberSpecification.getMinFractionDigits(),
        '0'
      );
    }

    return minorDigits;
  }

  /**
   * Get the CLDR formatting pattern.
   *
   * @see http://cldr.unicode.org/translation/number-patterns
   *
   * @param bool isNegative
   *                         If true, the negative pattern will be returned instead of the positive one
   *
   * @return string
   *                The CLDR formatting pattern
   */
  getCldrPattern(isNegative) {
    if (isNegative) {
      return this.numberSpecification.getNegativePattern();
    }

    return this.numberSpecification.getPositivePattern();
  }

  /**
   * Localize the passed number.
   *
   * If needed, occidental ("latn") digits are replaced with the relevant
   * ones (for instance with arab digits).
   * Symbol placeholders will also be replaced by the real symbols (configured
   * in number specification)
   *
   * @param string number
   *                       The number to be processed
   *
   * @return string
   *                The number after digits and symbols replacement
   */
  localizeNumber(number) {
    // If locale uses non-latin digits
    number = this.replaceDigits(number);

    // Placeholders become real localized symbols
    number = this.replaceSymbols(number);

    return number;
  }

  /**
   * Replace latin digits with relevant numbering system's digits.
   *
   * @param string number
   *                       The number to process
   *
   * @return string
   *                The number with replaced digits
   */
  replaceDigits(number) {
    // TODO use digits set from the locale (cf. /localization/CLDR/core/common/supplemental/numberingSystems.xml)
    return number;
  }

  /**
   * Replace placeholder number symbols with relevant numbering system's symbols.
   *
   * @param string number
   *                       The number to process
   *
   * @return string
   *                The number with replaced symbols
   */
  replaceSymbols(number) {
    symbols = this.numberSpecification.getSymbolsByNumberingSystem($this.numberingSystem);

    return number.replace(this.DECIMAL_SEPARATOR_PLACEHOLDER, symbols.getDecimal())
                 .replace(this.GROUP_SEPARATOR_PLACEHOLDER, symbols.getGroup())
                 .replace(this.MINUS_SIGN_PLACEHOLDER, symbols.getMinusSign())
                 .replace(this.PERCENT_SYMBOL_PLACEHOLDER, symbols.getPercentSign())
                 .replace(this.PLUS_SIGN_PLACEHOLDER, symbols.getPlusSign());
  }

  /**
   * Add missing placeholders to the number using the passed CLDR pattern.
   *
   * Missing placeholders can be the percent sign, currency symbol, etc.
   *
   * e.g. with a currency CLDR pattern:
   *  - Passed number (partially formatted): 1,234.567
   *  - Returned number: 1,234.567 ¤
   *  ("¤" symbol is the currency symbol placeholder)
   *
   * @see http://cldr.unicode.org/translation/number-patterns
   *
   * @param formattedNumber
   *  Number to process
   * @param pattern
   *  CLDR formatting pattern to use
   *
   * @return string
   */
  addPlaceholders(formattedNumber, pattern) {
    /*
     * Regex groups explanation:
     * #          : literal "#" character. Once.
     * (,#+)*     : any other "#" characters group, separated by ",". Zero to infinity times.
     * 0          : literal "0" character. Once.
     * (\.[0#]+)* : any combination of "0" and "#" characters groups, separated by '.'. Zero to infinity times.
     */
    return /#?(,#+)*0(\.[0#]+)*/.replace(formattedNumber, pattern);
  }

  /**
   * Perform some more specific replacements.
   *
   * Specific replacements are needed when number specification is extended.
   * For instance, prices have an extended number specification in order to
   * add currency symbol to the formatted number.
   *
   * @param string formattedNumber
   *
   * @return mixed
   */
  performSpecificReplacements(formattedNumber, symbol) {
    return formattedNumber.replace(
      this.CURRENCY_SYMBOL_PLACEHOLDER,
      symbol
    );
  }
}
