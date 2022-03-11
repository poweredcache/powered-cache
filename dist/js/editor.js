/*! For license information please see editor.js.LICENSE.txt */
!function(){"use strict";var e={418:function(e){var t=Object.getOwnPropertySymbols,r=Object.prototype.hasOwnProperty,o=Object.prototype.propertyIsEnumerable;function n(e){if(null===e||void 0===e)throw new TypeError("Object.assign cannot be called with null or undefined");return Object(e)}e.exports=function(){try{if(!Object.assign)return!1;var e=new String("abc");if(e[5]="de","5"===Object.getOwnPropertyNames(e)[0])return!1;for(var t={},r=0;r<10;r++)t["_"+String.fromCharCode(r)]=r;if("0123456789"!==Object.getOwnPropertyNames(t).map((function(e){return t[e]})).join(""))return!1;var o={};return"abcdefghijklmnopqrst".split("").forEach((function(e){o[e]=e})),"abcdefghijklmnopqrst"===Object.keys(Object.assign({},o)).join("")}catch(e){return!1}}()?Object.assign:function(e,c){for(var i,a,s=n(e),l=1;l<arguments.length;l++){for(var p in i=Object(arguments[l]))r.call(i,p)&&(s[p]=i[p]);if(t){a=t(i);for(var u=0;u<a.length;u++)o.call(i,a[u])&&(s[a[u]]=i[a[u]])}}return s}},408:function(e,t,r){var o=r(418),n=60103,c=60106;var i=60109,a=60110,s=60112;var l=60115,p=60116;if("function"===typeof Symbol&&Symbol.for){var u=Symbol.for;n=u("react.element"),c=u("react.portal"),u("react.fragment"),u("react.strict_mode"),u("react.profiler"),i=u("react.provider"),a=u("react.context"),s=u("react.forward_ref"),u("react.suspense"),l=u("react.memo"),p=u("react.lazy")}var f="function"===typeof Symbol&&Symbol.iterator;function d(e){for(var t="https://reactjs.org/docs/error-decoder.html?invariant="+e,r=1;r<arguments.length;r++)t+="&args[]="+encodeURIComponent(arguments[r]);return"Minified React error #"+e+"; visit "+t+" for the full message or use the non-minified dev environment for full errors and additional helpful warnings."}var _={isMounted:function(){return!1},enqueueForceUpdate:function(){},enqueueReplaceState:function(){},enqueueSetState:function(){}},h={};function b(e,t,r){this.props=e,this.context=t,this.refs=h,this.updater=r||_}function y(){}function w(e,t,r){this.props=e,this.context=t,this.refs=h,this.updater=r||_}b.prototype.isReactComponent={},b.prototype.setState=function(e,t){if("object"!==typeof e&&"function"!==typeof e&&null!=e)throw Error(d(85));this.updater.enqueueSetState(this,e,t,"setState")},b.prototype.forceUpdate=function(e){this.updater.enqueueForceUpdate(this,e,"forceUpdate")},y.prototype=b.prototype;var m=w.prototype=new y;m.constructor=w,o(m,b.prototype),m.isPureReactComponent=!0;var v={current:null},g=Object.prototype.hasOwnProperty,j={key:!0,ref:!0,__self:!0,__source:!0};function S(e,t,r){var o,c={},i=null,a=null;if(null!=t)for(o in void 0!==t.ref&&(a=t.ref),void 0!==t.key&&(i=""+t.key),t)g.call(t,o)&&!j.hasOwnProperty(o)&&(c[o]=t[o]);var s=arguments.length-2;if(1===s)c.children=r;else if(1<s){for(var l=Array(s),p=0;p<s;p++)l[p]=arguments[p+2];c.children=l}if(e&&e.defaultProps)for(o in s=e.defaultProps)void 0===c[o]&&(c[o]=s[o]);return{$$typeof:n,type:e,key:i,ref:a,props:c,_owner:v.current}}function k(e){return"object"===typeof e&&null!==e&&e.$$typeof===n}var O=/\/+/g;function P(e,t){return"object"===typeof e&&null!==e&&null!=e.key?function(e){var t={"=":"=0",":":"=2"};return"$"+e.replace(/[=:]/g,(function(e){return t[e]}))}(""+e.key):t.toString(36)}function C(e,t,r,o,i){var a=typeof e;"undefined"!==a&&"boolean"!==a||(e=null);var s=!1;if(null===e)s=!0;else switch(a){case"string":case"number":s=!0;break;case"object":switch(e.$$typeof){case n:case c:s=!0}}if(s)return i=i(s=e),e=""===o?"."+P(s,0):o,Array.isArray(i)?(r="",null!=e&&(r=e.replace(O,"$&/")+"/"),C(i,t,r,"",(function(e){return e}))):null!=i&&(k(i)&&(i=function(e,t){return{$$typeof:n,type:e.type,key:t,ref:e.ref,props:e.props,_owner:e._owner}}(i,r+(!i.key||s&&s.key===i.key?"":(""+i.key).replace(O,"$&/")+"/")+e)),t.push(i)),1;if(s=0,o=""===o?".":o+":",Array.isArray(e))for(var l=0;l<e.length;l++){var p=o+P(a=e[l],l);s+=C(a,t,r,p,i)}else if(p=function(e){return null===e||"object"!==typeof e?null:"function"===typeof(e=f&&e[f]||e["@@iterator"])?e:null}(e),"function"===typeof p)for(e=p.call(e),l=0;!(a=e.next()).done;)s+=C(a=a.value,t,r,p=o+P(a,l++),i);else if("object"===a)throw t=""+e,Error(d(31,"[object Object]"===t?"object with keys {"+Object.keys(e).join(", ")+"}":t));return s}function z(e,t,r){if(null==e)return e;var o=[],n=0;return C(e,o,"","",(function(e){return t.call(r,e,n++)})),o}function E(e){if(-1===e._status){var t=e._result;t=t(),e._status=0,e._result=t,t.then((function(t){0===e._status&&(t=t.default,e._status=1,e._result=t)}),(function(t){0===e._status&&(e._status=2,e._result=t)}))}if(1===e._status)return e._result;throw e._result}var x={current:null};function $(){var e=x.current;if(null===e)throw Error(d(321));return e}t.createElement=S},294:function(e,t,r){e.exports=r(408)}},t={};function r(o){var n=t[o];if(void 0!==n)return n.exports;var c=t[o]={exports:{}};return e[o](c,c.exports,r),c.exports}!function(){var e=r(294);const{__:t}=wp.i18n,{PluginDocumentSettingPanel:o}=wp.editPost,{CheckboxControl:n}=wp.components,{dispatch:c,useSelect:i}=wp.data,{registerPlugin:a}=wp.plugins;a("powered-cache-post-meta",{render:()=>{const r=i((e=>e("core/editor").getEditedPostAttribute("meta")));if(!r)return null;if(!("powered_cache_disable_cache"in r)&&!("powered_cache_disable_lazyload"in r)&&!("powered_cache_disable_css_optimization"in r)&&!("powered_cache_disable_js_optimization"in r)&&!("powered_cache_disable_critical_css"in r)&&!("powered_cache_specific_critical_css"in r))return null;const a=r.powered_cache_disable_cache||!1,s=r.powered_cache_disable_lazyload||!1,l=r.powered_cache_disable_css_optimization||!1,p=r.powered_cache_disable_js_optimization||!1,u=r.powered_cache_disable_critical_css||!1,f=r.powered_cache_specific_critical_css||!1;return(0,e.createElement)(o,{icon:"superhero",title:t("Powered Cache","powered-cache"),className:"powered-cache-panel"},"powered_cache_disable_cache"in r&&(0,e.createElement)(n,{label:t("Don't cache this post","powered-cache"),checked:a,onChange:()=>{c("core/editor").editPost({meta:{powered_cache_disable_cache:!a}})}}),"powered_cache_disable_lazyload"in r&&(0,e.createElement)(n,{label:t("Disable lazy loading for this post","powered-cache"),checked:s,onChange:()=>{c("core/editor").editPost({meta:{powered_cache_disable_lazyload:!s}})}}),"powered_cache_disable_css_optimization"in r&&(0,e.createElement)(n,{label:t("Disable CSS optimization","powered-cache"),checked:l,onChange:()=>{c("core/editor").editPost({meta:{powered_cache_disable_css_optimization:!l}})}}),"powered_cache_disable_js_optimization"in r&&(0,e.createElement)(n,{label:t("Disable JS optimization","powered-cache"),checked:p,onChange:()=>{c("core/editor").editPost({meta:{powered_cache_disable_js_optimization:!p}})}}),"powered_cache_disable_critical_css"in r&&!f&&(0,e.createElement)(n,{label:t("Disable Critical CSS for this post","powered-cache"),checked:u,onChange:()=>{c("core/editor").editPost({meta:{powered_cache_disable_critical_css:!u}})}}),"powered_cache_specific_critical_css"in r&&!u&&(0,e.createElement)(n,{label:t("Generate specific Critical CSS","powered-cache"),checked:f,onChange:()=>{c("core/editor").editPost({meta:{powered_cache_specific_critical_css:!f}})}}))}})}()}();