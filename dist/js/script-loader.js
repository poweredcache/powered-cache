/*!@shinsenter/defer.js@3.8.0*/
!(function(r,f,u){function s(e,n,t,i){I?q(e,n):(1<(t=t===u?s.lazy:t)&&(i=e,N.push(e=function(){i&&(i(),i=u)},t)),(t?S:N).push(e,Math.max(t?350:0,n)))}function c(e){return"string"==typeof(e=e||{})?{id:e}:e}function a(n,e,t,i){l(e.split(" "),function(e){(i||r)[n+"EventListener"](e,t||o)})}function l(e,n){e.map(n)}function d(e,n){l(z.call(e.attributes),function(e){n(e.name,e.value)})}function p(e,n,t,i,o,r){if(o=E.createElement(e),t&&a(w,b,t,o),n)for(r in n)o[j](r,n[r]);return i&&E.head.appendChild(o),o}function m(e,n){return z.call((n||E).querySelectorAll(e))}function h(i,e){l(m("source,img",i),h),d(i,function(e,n,t){(t=y.exec(e))&&i[j](t[1],n)}),"string"==typeof e&&(i.className+=" "+e),i[b]&&i[b]()}function e(e,n,t){s(function(i){l(i=m(e||"script[type=deferjs]"),function(e,t){e[A]&&(t={},d(e,function(e,n){e!=C&&(t[e==A?"href":e]=n)}),t.as=g,t.rel="preload",p(v,t,u,r))}),(function o(e,t,n){(e=i[k]())&&(t={},d(e,function(e,n){e!=C&&(t[e]=n)}),n=t[A]&&!("async"in t),(t=p(g,t)).text=e.text,e.parentNode.replaceChild(t,e),n?a(w,b+" error",o,t):o())})()},n,t)}function o(e,n){for(n=I?(a(t,i),S):(a(t,x),I=s,S[0]&&a(w,i),N);n[0];)q(n[k](),n[k]())}var y=/^data-(.+)/,v="link",g="script",b="load",n="pageshow",w="add",t="remove",i="touchstart mousemove mousedown keydown wheel",x="on"+n in r?n:b,j="setAttribute",k="shift",A="src",C="type",D=r.IntersectionObserver,E=r.document,I=/p/.test(E.readyState),N=[],S=[],q=r.setTimeout,z=N.slice;s.all=e,s.dom=function(e,n,o,r,c){s(function(t){function i(e){r&&!1===r(e)||h(e,o)}t=D?new D(function(e){l(e,function(e,n){e.isIntersecting&&(t.unobserve(n=e.target),i(n))})},c):u,l(m(e||"[data-src]"),function(e){e[f]||(e[f]=s,t?t.observe(e):i(e))})},n,!1)},s.css=function(e,n,t,i,o){(n=c(n)).href=e,n.rel="stylesheet",s(function(){p(v,n,i,r)},t,o)},s.js=function(e,n,t,i,o){(n=c(n)).src=e,s(function(){p(g,n,i,r)},t,o)},s.reveal=h,r[f]=s,I||a(w,x),e()})(this,"Defer"),(function(e,n){n=e.defer=e.Defer,e.deferimg=e.deferiframe=n.dom,e.deferstyle=n.css,e.deferscript=n.js})(this);