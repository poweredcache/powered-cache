/*! For license information please see editor.js.LICENSE.txt */
!function(){"use strict";var e={7418:function(e){var t=Object.getOwnPropertySymbols,r=Object.prototype.hasOwnProperty,n=Object.prototype.propertyIsEnumerable;function o(e){if(null===e||void 0===e)throw new TypeError("Object.assign cannot be called with null or undefined");return Object(e)}e.exports=function(){try{if(!Object.assign)return!1;var e=new String("abc");if(e[5]="de","5"===Object.getOwnPropertyNames(e)[0])return!1;for(var t={},r=0;r<10;r++)t["_"+String.fromCharCode(r)]=r;if("0123456789"!==Object.getOwnPropertyNames(t).map((function(e){return t[e]})).join(""))return!1;var n={};return"abcdefghijklmnopqrst".split("").forEach((function(e){n[e]=e})),"abcdefghijklmnopqrst"===Object.keys(Object.assign({},n)).join("")}catch(e){return!1}}()?Object.assign:function(e,a){for(var c,i,u=o(e),l=1;l<arguments.length;l++){for(var s in c=Object(arguments[l]))r.call(c,s)&&(u[s]=c[s]);if(t){i=t(c);for(var f=0;f<i.length;f++)n.call(c,i[f])&&(u[i[f]]=c[i[f]])}}return u}},2408:function(e,t,r){var n=r(7418),o=60103,a=60106;var c=60109,i=60110,u=60112;var l=60115,s=60116;if("function"===typeof Symbol&&Symbol.for){var f=Symbol.for;o=f("react.element"),a=f("react.portal"),f("react.fragment"),f("react.strict_mode"),f("react.profiler"),c=f("react.provider"),i=f("react.context"),u=f("react.forward_ref"),f("react.suspense"),l=f("react.memo"),s=f("react.lazy")}var p="function"===typeof Symbol&&Symbol.iterator;function d(e){for(var t="https://reactjs.org/docs/error-decoder.html?invariant="+e,r=1;r<arguments.length;r++)t+="&args[]="+encodeURIComponent(arguments[r]);return"Minified React error #"+e+"; visit "+t+" for the full message or use the non-minified dev environment for full errors and additional helpful warnings."}var h={isMounted:function(){return!1},enqueueForceUpdate:function(){},enqueueReplaceState:function(){},enqueueSetState:function(){}},y={};function b(e,t,r){this.props=e,this.context=t,this.refs=y,this.updater=r||h}function _(){}function v(e,t,r){this.props=e,this.context=t,this.refs=y,this.updater=r||h}b.prototype.isReactComponent={},b.prototype.setState=function(e,t){if("object"!==typeof e&&"function"!==typeof e&&null!=e)throw Error(d(85));this.updater.enqueueSetState(this,e,t,"setState")},b.prototype.forceUpdate=function(e){this.updater.enqueueForceUpdate(this,e,"forceUpdate")},_.prototype=b.prototype;var w=v.prototype=new _;w.constructor=v,n(w,b.prototype),w.isPureReactComponent=!0;var m={current:null},g=Object.prototype.hasOwnProperty,j={key:!0,ref:!0,__self:!0,__source:!0};function O(e,t,r){var n,a={},c=null,i=null;if(null!=t)for(n in void 0!==t.ref&&(i=t.ref),void 0!==t.key&&(c=""+t.key),t)g.call(t,n)&&!j.hasOwnProperty(n)&&(a[n]=t[n]);var u=arguments.length-2;if(1===u)a.children=r;else if(1<u){for(var l=Array(u),s=0;s<u;s++)l[s]=arguments[s+2];a.children=l}if(e&&e.defaultProps)for(n in u=e.defaultProps)void 0===a[n]&&(a[n]=u[n]);return{$$typeof:o,type:e,key:c,ref:i,props:a,_owner:m.current}}function k(e){return"object"===typeof e&&null!==e&&e.$$typeof===o}var S=/\/+/g;function P(e,t){return"object"===typeof e&&null!==e&&null!=e.key?function(e){var t={"=":"=0",":":"=2"};return"$"+e.replace(/[=:]/g,(function(e){return t[e]}))}(""+e.key):t.toString(36)}function x(e,t,r,n,c){var i=typeof e;"undefined"!==i&&"boolean"!==i||(e=null);var u=!1;if(null===e)u=!0;else switch(i){case"string":case"number":u=!0;break;case"object":switch(e.$$typeof){case o:case a:u=!0}}if(u)return c=c(u=e),e=""===n?"."+P(u,0):n,Array.isArray(c)?(r="",null!=e&&(r=e.replace(S,"$&/")+"/"),x(c,t,r,"",(function(e){return e}))):null!=c&&(k(c)&&(c=function(e,t){return{$$typeof:o,type:e.type,key:t,ref:e.ref,props:e.props,_owner:e._owner}}(c,r+(!c.key||u&&u.key===c.key?"":(""+c.key).replace(S,"$&/")+"/")+e)),t.push(c)),1;if(u=0,n=""===n?".":n+":",Array.isArray(e))for(var l=0;l<e.length;l++){var s=n+P(i=e[l],l);u+=x(i,t,r,s,c)}else if("function"===typeof(s=function(e){return null===e||"object"!==typeof e?null:"function"===typeof(e=p&&e[p]||e["@@iterator"])?e:null}(e)))for(e=s.call(e),l=0;!(i=e.next()).done;)u+=x(i=i.value,t,r,s=n+P(i,l++),c);else if("object"===i)throw t=""+e,Error(d(31,"[object Object]"===t?"object with keys {"+Object.keys(e).join(", ")+"}":t));return u}function E(e,t,r){if(null==e)return e;var n=[],o=0;return x(e,n,"","",(function(e){return t.call(r,e,o++)})),n}function $(e){if(-1===e._status){var t=e._result;t=t(),e._status=0,e._result=t,t.then((function(t){0===e._status&&(t=t.default,e._status=1,e._result=t)}),(function(t){0===e._status&&(e._status=2,e._result=t)}))}if(1===e._status)return e._result;throw e._result}var C={current:null};function q(){var e=C.current;if(null===e)throw Error(d(321));return e}t.createElement=O},7294:function(e,t,r){e.exports=r(2408)}},t={};function r(n){var o=t[n];if(void 0!==o)return o.exports;var a=t[n]={exports:{}};return e[n](a,a.exports,r),a.exports}!function(){var e=r(7294),t=wp.i18n.__,n=wp.editPost.PluginDocumentSettingPanel,o=wp.components.CheckboxControl,a=wp.data,c=a.dispatch,i=a.useSelect;(0,wp.plugins.registerPlugin)("powered-cache-post-meta",{render:function(){var r=i((function(e){return e("core/editor").getEditedPostAttribute("meta")})),a=r.powered_cache_disable_cache||!1,u=r.powered_cache_disable_lazyload||!1;return"powered_cache_disable_cache"in r||"powered_cache_disable_lazyload"in r?(0,e.createElement)(n,{icon:"superhero",title:t("Powered Cache","powered-cache"),className:"powered-cache-panel"},"powered_cache_disable_cache"in r&&(0,e.createElement)(o,{label:t("Don't cache this post","powered-cache"),checked:a,onChange:function(){c("core/editor").editPost({meta:{powered_cache_disable_cache:!a}})}}),"powered_cache_disable_lazyload"in r&&(0,e.createElement)(o,{label:t("Disable lazy loading for this post","powered-cache"),checked:u,onChange:function(){c("core/editor").editPost({meta:{powered_cache_disable_lazyload:!u}})}})):null}})}()}();