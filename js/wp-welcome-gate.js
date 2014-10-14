jQuery(document).ready(function(){
    var currFFZoom = 1;
    var currIEZoom = 100;

    if (jQuery.browser.mozilla){
        var step = 0.2;
        currFFZoom -= step;                 
        jQuery('#toBeZoomedOut').css('MozTransform','scale(' + currFFZoom + ')');
    } else {
        var step = 2;
        currIEZoom -= step;
        jQuery('#toBeZoomedOut').css('zoom', ' ' + currIEZoom + '%');
    }
});