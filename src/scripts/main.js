(function(window, document, hljs, Snap) {
  /**
   * init source highlight
   */
  hljs.initHighlightingOnLoad();


  /**
   * animate logo on page-load
   */
  window.addEventListener('load', function() {

    /**
     * do animation on svg
     */
    function doAnimation() {
      // init canvas from svg
      var canvas = Snap('#hero-logo-animated');
      if (!canvas) {
        return;
      }
      // grab existing elements from svg
      var shadow = canvas.select('#Shadow');
      var halo = canvas.select('#Halo');
      var p = canvas.select('#P');

      // set helper mask for shadow animation
      var circle = canvas.circle('50%', '45%', 0).attr({'fill': '#fff'});
      shadow.attr({mask: circle});

      // animate
      var speed = 500;
      var easing = mina.easeout();

      var animation1 = function() {
        p.animate({opacity: 1}, speed/2, easing, animation2);
      }

      var animation2 = function() {
        shadow.animate({fillOpacity: 1}, 2*speed, easing);
        halo.animate({fillOpacity: 0.7}, 2*speed, easing);
        circle.animate({r: '60%', fillOpacity: 1}, speed, easing);
      }

      animation1();
    }

    /**
     * wait for the svg to be loaded
     */
    function start() {
      var object = document.getElementById('hero-logo-animated');
      if (!object) {
        return;
      }
      var svg = object.contentDocument;
      if (!svg) {
        setTimeout(start, 300);
      }  else {
        doAnimation();
      }
    }

    start();
  });
})(window, document, hljs, Snap);
