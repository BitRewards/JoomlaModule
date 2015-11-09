(function(w, d){
 w.giftdAsync = d.cookie.indexOf('giftd_s=') === -1;
 var rnd = (d.cookie.indexOf('giftd_nocache=') !== -1) ? ("&" + Date.now()) : "";
 d.write(
 '<' + 'script src="https://giftd.ru/widgets/js/v2?pid=bitrix-test' + rnd + '" id="giftd-script" crossorigin="anonymous" ' + (window.giftdAsync ? 'async="async"' : '') + '><' + '\/script>'
    );
})(window, document);