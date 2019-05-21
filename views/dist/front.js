!function(t){var e={};function i(n){if(e[n])return e[n].exports;var r=e[n]={i:n,l:!1,exports:{}};return t[n].call(r.exports,r,r.exports,i),r.l=!0,r.exports}i.m=t,i.c=e,i.d=function(t,e,n){i.o(t,e)||Object.defineProperty(t,e,{enumerable:!0,get:n})},i.r=function(t){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})},i.t=function(t,e){if(1&e&&(t=i(t)),8&e)return t;if(4&e&&"object"==typeof t&&t&&t.__esModule)return t;var n=Object.create(null);if(i.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:t}),2&e&&"string"!=typeof t)for(var r in t)i.d(n,r,function(e){return t[e]}.bind(null,r));return n},i.n=function(t){var e=t&&t.__esModule?function(){return t.default}:function(){return t};return i.d(e,"a",e),e},i.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},i.p="",i(i.s=11)}([function(t,e,i){var n,r,s={},o=(n=function(){return window&&document&&document.all&&!window.atob},function(){return void 0===r&&(r=n.apply(this,arguments)),r}),a=function(t){var e={};return function(t,i){if("function"==typeof t)return t();if(void 0===e[t]){var n=function(t,e){return e?e.querySelector(t):document.querySelector(t)}.call(this,t,i);if(window.HTMLIFrameElement&&n instanceof window.HTMLIFrameElement)try{n=n.contentDocument.head}catch(t){n=null}e[t]=n}return e[t]}}(),c=null,l=0,u=[],p=i(1);function f(t,e){for(var i=0;i<t.length;i++){var n=t[i],r=s[n.id];if(r){r.refs++;for(var o=0;o<r.parts.length;o++)r.parts[o](n.parts[o]);for(;o<n.parts.length;o++)r.parts.push(y(n.parts[o],e))}else{var a=[];for(o=0;o<n.parts.length;o++)a.push(y(n.parts[o],e));s[n.id]={id:n.id,refs:1,parts:a}}}}function d(t,e){for(var i=[],n={},r=0;r<t.length;r++){var s=t[r],o=e.base?s[0]+e.base:s[0],a={css:s[1],media:s[2],sourceMap:s[3]};n[o]?n[o].parts.push(a):i.push(n[o]={id:o,parts:[a]})}return i}function h(t,e){var i=a(t.insertInto);if(!i)throw new Error("Couldn't find a style target. This probably means that the value for the 'insertInto' parameter is invalid.");var n=u[u.length-1];if("top"===t.insertAt)n?n.nextSibling?i.insertBefore(e,n.nextSibling):i.appendChild(e):i.insertBefore(e,i.firstChild),u.push(e);else if("bottom"===t.insertAt)i.appendChild(e);else{if("object"!=typeof t.insertAt||!t.insertAt.before)throw new Error("[Style Loader]\n\n Invalid value for parameter 'insertAt' ('options.insertAt') found.\n Must be 'top', 'bottom', or Object.\n (https://github.com/webpack-contrib/style-loader#insertat)\n");var r=a(t.insertAt.before,i);i.insertBefore(e,r)}}function g(t){if(null===t.parentNode)return!1;t.parentNode.removeChild(t);var e=u.indexOf(t);e>=0&&u.splice(e,1)}function m(t){var e=document.createElement("style");if(void 0===t.attrs.type&&(t.attrs.type="text/css"),void 0===t.attrs.nonce){var n=function(){0;return i.nc}();n&&(t.attrs.nonce=n)}return v(e,t.attrs),h(t,e),e}function v(t,e){Object.keys(e).forEach(function(i){t.setAttribute(i,e[i])})}function y(t,e){var i,n,r,s;if(e.transform&&t.css){if(!(s="function"==typeof e.transform?e.transform(t.css):e.transform.default(t.css)))return function(){};t.css=s}if(e.singleton){var o=l++;i=c||(c=m(e)),n=w.bind(null,i,o,!1),r=w.bind(null,i,o,!0)}else t.sourceMap&&"function"==typeof URL&&"function"==typeof URL.createObjectURL&&"function"==typeof URL.revokeObjectURL&&"function"==typeof Blob&&"function"==typeof btoa?(i=function(t){var e=document.createElement("link");return void 0===t.attrs.type&&(t.attrs.type="text/css"),t.attrs.rel="stylesheet",v(e,t.attrs),h(t,e),e}(e),n=function(t,e,i){var n=i.css,r=i.sourceMap,s=void 0===e.convertToAbsoluteUrls&&r;(e.convertToAbsoluteUrls||s)&&(n=p(n));r&&(n+="\n/*# sourceMappingURL=data:application/json;base64,"+btoa(unescape(encodeURIComponent(JSON.stringify(r))))+" */");var o=new Blob([n],{type:"text/css"}),a=t.href;t.href=URL.createObjectURL(o),a&&URL.revokeObjectURL(a)}.bind(null,i,e),r=function(){g(i),i.href&&URL.revokeObjectURL(i.href)}):(i=m(e),n=function(t,e){var i=e.css,n=e.media;n&&t.setAttribute("media",n);if(t.styleSheet)t.styleSheet.cssText=i;else{for(;t.firstChild;)t.removeChild(t.firstChild);t.appendChild(document.createTextNode(i))}}.bind(null,i),r=function(){g(i)});return n(t),function(e){if(e){if(e.css===t.css&&e.media===t.media&&e.sourceMap===t.sourceMap)return;n(t=e)}else r()}}t.exports=function(t,e){if("undefined"!=typeof DEBUG&&DEBUG&&"object"!=typeof document)throw new Error("The style-loader cannot be used in a non-browser environment");(e=e||{}).attrs="object"==typeof e.attrs?e.attrs:{},e.singleton||"boolean"==typeof e.singleton||(e.singleton=o()),e.insertInto||(e.insertInto="head"),e.insertAt||(e.insertAt="bottom");var i=d(t,e);return f(i,e),function(t){for(var n=[],r=0;r<i.length;r++){var o=i[r];(a=s[o.id]).refs--,n.push(a)}t&&f(d(t,e),e);for(r=0;r<n.length;r++){var a;if(0===(a=n[r]).refs){for(var c=0;c<a.parts.length;c++)a.parts[c]();delete s[a.id]}}}};var b,S=(b=[],function(t,e){return b[t]=e,b.filter(Boolean).join("\n")});function w(t,e,i,n){var r=i?"":n.css;if(t.styleSheet)t.styleSheet.cssText=S(e,r);else{var s=document.createTextNode(r),o=t.childNodes;o[e]&&t.removeChild(o[e]),o.length?t.insertBefore(s,o[e]):t.appendChild(s)}}},function(t,e){t.exports=function(t){var e="undefined"!=typeof window&&window.location;if(!e)throw new Error("fixUrls requires window.location");if(!t||"string"!=typeof t)return t;var i=e.protocol+"//"+e.host,n=i+e.pathname.replace(/\/[^\/]*$/,"/");return t.replace(/url\s*\(((?:[^)(]|\((?:[^)(]+|\([^)(]*\))*\))*)\)/gi,function(t,e){var r,s=e.trim().replace(/^"(.*)"$/,function(t,e){return e}).replace(/^'(.*)'$/,function(t,e){return e});return/^(#|data:|http:\/\/|https:\/\/|file:\/\/\/|\s*$)/i.test(s)?t:(r=0===s.indexOf("//")?s:0===s.indexOf("/")?i+s:n+s.replace(/^\.\//,""),"url("+JSON.stringify(r)+")")})}},function(t,e,i){var n=i(3);"string"==typeof n&&(n=[[t.i,n,""]]);var r={hmr:!0,transform:void 0,insertInto:void 0};i(0)(n,r);n.locals&&(t.exports=n.locals)},function(t,e,i){},function(t,e,i){var n=i(5);"string"==typeof n&&(n=[[t.i,n,""]]);var r={hmr:!0,transform:void 0,insertInto:void 0};i(0)(n,r);n.locals&&(t.exports=n.locals)},function(t,e,i){},function(t,e,i){var n=i(7);"string"==typeof n&&(n=[[t.i,n,""]]);var r={hmr:!0,transform:void 0,insertInto:void 0};i(0)(n,r);n.locals&&(t.exports=n.locals)},function(t,e,i){},,,,function(t,e,i){"use strict";i.r(e);var n=t=>t.split("&").map(t=>{const[e,i]=t.split("=");return{name:e,value:decodeURIComponent(i).replace(/\+/g," ")}});
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */var r=class{constructor(t){this.message=t,this.name="LocalizationException"}};
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */var s=class{constructor(t,e,i,n,r,s,o,a,c,l,u){this.decimal=t,this.group=e,this.list=i,this.percentSign=n,this.minusSign=r,this.plusSign=s,this.exponential=o,this.superscriptingExponent=a,this.perMille=c,this.infinity=l,this.nan=u,this.validateData()}getDecimal(){return this.decimal}getGroup(){return this.group}getList(){return this.list}getPercentSign(){return this.percentSign}getMinusSign(){return this.minusSign}getPlusSign(){return this.plusSign}getExponential(){return this.exponential}getSuperscriptingExponent(){return this.superscriptingExponent}getPerMille(){return this.perMille}getInfinity(){return this.infinity}getNan(){return this.nan}validateData(){if(!this.decimal||"string"!=typeof this.decimal)throw new r("Invalid decimal");if(!this.group||"string"!=typeof this.group)throw new r("Invalid group");if(!this.list||"string"!=typeof this.list)throw new r("Invalid symbol list");if(!this.percentSign||"string"!=typeof this.percentSign)throw new r("Invalid percentSign");if(!this.minusSign||"string"!=typeof this.minusSign)throw new r("Invalid minusSign");if(!this.plusSign||"string"!=typeof this.plusSign)throw new r("Invalid plusSign");if(!this.exponential||"string"!=typeof this.exponential)throw new r("Invalid exponential");if(!this.superscriptingExponent||"string"!=typeof this.superscriptingExponent)throw new r("Invalid superscriptingExponent");if(!this.perMille||"string"!=typeof this.perMille)throw new r("Invalid perMille");if(!this.infinity||"string"!=typeof this.infinity)throw new r("Invalid infinity");if(!this.nan||"string"!=typeof this.nan)throw new r("Invalid nan")}};
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */var o=class{constructor(t,e,i,n,o,a,c,l){if(this.positivePattern=t,this.negativePattern=e,this.symbol=i,this.maxFractionDigits=n,this.minFractionDigits=n<o?n:o,this.groupingUsed=a,this.primaryGroupSize=c,this.secondaryGroupSize=l,!this.positivePattern||"string"!=typeof this.positivePattern)throw new r("Invalid positivePattern");if(!this.negativePattern||"string"!=typeof this.negativePattern)throw new r("Invalid negativePattern");if(!(this.symbol&&this.symbol instanceof s))throw new r("Invalid symbol");if("number"!=typeof this.maxFractionDigits)throw new r("Invalid maxFractionDigits");if("number"!=typeof this.minFractionDigits)throw new r("Invalid minFractionDigits");if("boolean"!=typeof this.groupingUsed)throw new r("Invalid groupingUsed");if("number"!=typeof this.primaryGroupSize)throw new r("Invalid primaryGroupSize");if("number"!=typeof this.secondaryGroupSize)throw new r("Invalid secondaryGroupSize")}getSymbol(){return this.symbol}getPositivePattern(){return this.positivePattern}getNegativePattern(){return this.negativePattern}getMaxFractionDigits(){return this.maxFractionDigits}getMinFractionDigits(){return this.minFractionDigits}isGroupingUsed(){return this.groupingUsed}getPrimaryGroupSize(){return this.primaryGroupSize}getSecondaryGroupSize(){return this.secondaryGroupSize}};
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */const a="symbol";var c=class extends o{constructor(t,e,i,n,s,o,a,c,l,u){if(super(t,e,i,n,s,o,a,c),this.currencySymbol=l,this.currencyCode=u,!this.currencySymbol||"string"!=typeof this.currencySymbol)throw new r("Invalid currencySymbol");if(!this.currencyCode||"string"!=typeof this.currencyCode)throw new r("Invalid currencyCode")}static getCurrencyDisplay(){return a}getCurrencySymbol(){return this.currencySymbol}getCurrencyCode(){return this.currencyCode}};
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */const l="¤",u=".",p=",",f="-",d="%",h="+";class g{constructor(t){this.numberSpecification=t}format(t,e){void 0!==e&&(this.numberSpecification=e);const i=Math.abs(t).toFixed(this.numberSpecification.getMaxFractionDigits());let[n,r]=this.extractMajorMinorDigits(i),s=n=this.splitMajorGroups(n);(r=this.adjustMinorDigitsZeroes(r))&&(s+=u+r);const o=this.getCldrPattern(n<0);return s=this.addPlaceholders(s,o),s=this.replaceSymbols(s),s=this.performSpecificReplacements(s)}extractMajorMinorDigits(t){const e=t.toString().split(".");return[e[0],void 0===e[1]?"":e[1]]}splitMajorGroups(t){if(!this.numberSpecification.isGroupingUsed())return t;const e=t.split("").reverse();let i=[];for(i.push(e.splice(0,this.numberSpecification.getPrimaryGroupSize()));e.length;)i.push(e.splice(0,this.numberSpecification.getSecondaryGroupSize()));i=i.reverse();const n=[];return i.forEach(t=>{n.push(t.reverse().join(""))}),n.join(p)}adjustMinorDigitsZeroes(t){let e=t;return e.length>this.numberSpecification.getMaxFractionDigits()&&(e=e.replace(/0+$/,"")),e.length<this.numberSpecification.getMinFractionDigits()&&(e=e.padEnd(this.numberSpecification.getMinFractionDigits(),"0")),e}getCldrPattern(t){return t?this.numberSpecification.getNegativePattern():this.numberSpecification.getPositivePattern()}replaceSymbols(t){const e=this.numberSpecification.getSymbol();let i=t;return i=(i=(i=(i=(i=i.split(u).join(e.getDecimal())).split(p).join(e.getGroup())).split(f).join(e.getMinusSign())).split(d).join(e.getPercentSign())).split(h).join(e.getPlusSign())}addPlaceholders(t,e){return e.replace(/#?(,#+)*0(\.[0#]+)*/,t)}performSpecificReplacements(t){return this.numberSpecification instanceof c?t.split(l).join(this.numberSpecification.getCurrencySymbol()):t}static build(t){const e=new s(...t.symbol);let i;return i=t.currencySymbol?new c(t.positivePattern,t.negativePattern,e,parseInt(t.maxFractionDigits,10),parseInt(t.minFractionDigits,10),t.groupingUsed,t.primaryGroupSize,t.secondaryGroupSize,t.currencySymbol,t.currencyCode):new o(t.positivePattern,t.negativePattern,e,parseInt(t.maxFractionDigits,10),parseInt(t.minFractionDigits,10),t.groupingUsed,t.primaryGroupSize,t.secondaryGroupSize),new g(i)}}var m=g;
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */let v;const y=(t,e,i)=>{void 0===v?t.text(t.text().replace(/([^\d]*)(?:[\d .,]+)([^\d]+)(?:[\d .,]+)(.*)/,`$1${e}$2${i}$3`)):t.text(`${v.format(e)} - ${v.format(i)}`)};var b=()=>{$(".faceted-slider").each(function(){const t=$(this),e=t.data("slider-values"),i=t.data("slider-specifications");null!=i&&(v=m.build(i)),y($(`#facet_label_${t.data("slider-id")}`),null===e?t.data("slider-min"):e[0],null===e?t.data("slider-max"):e[1]),$(`#slider-range_${t.data("slider-id")}`).slider({range:!0,min:t.data("slider-min"),max:t.data("slider-max"),values:[null===e?t.data("slider-min"):e[0],null===e?t.data("slider-max"):e[1]],change(e,i){const r=t.data("slider-encoded-url").split("?");let s=[];r.length>1&&(s=n(r[1]));let o=!1;s.forEach(t=>{"q"===t.name&&(o=!0)}),o||s.push({name:"q",value:""}),s.forEach(e=>{"q"===e.name&&(e.value+=[e.value.length>0?"/":"",t.data("slider-label"),"-",t.data("slider-unit"),"-",i.values[0],"-",i.values[1]].join(""))});const a=[r[0],"?",$.param(s)].join("");prestashop.emit("updateFacets",a)},slide(e,i){y($(`#facet_label_${t.data("slider-id")}`),i.values[0],i.values[1])}})})};i(2);
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
const S='<div class="faceted-overlay">\n<div class="overlay__inner">\n<div class="overlay__content"><span class="spinner"></span></div>\n</div>\n</div>';
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
$(document).ready(()=>{prestashop.on("updateProductList",()=>{$(".faceted-overlay").remove(),b()}),b(),prestashop.on("updateFacets",()=>{1!==$(".faceted-overlay").length&&$("body").append(S)})});i(4),i(6)}]);
//# sourceMappingURL=front.js.map