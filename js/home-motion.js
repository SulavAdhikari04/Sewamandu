/* ============================================================
   Sewamandu — Cinematic Scroll Engine (vanilla JS)
   · rAF-batched parallax (translate3d, GPU friendly)
   · IntersectionObserver reveals (zoom / fade / stagger)
   · Scroll-linked progress for expand-on-scroll & dolly zoom
   · Honors prefers-reduced-motion
   ============================================================ */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* -------- 1. Reveal on scroll (fade / zoom / stagger) -------- */
  function initReveals() {
    var targets = document.querySelectorAll(".reveal, .reveal-zoom, .stagger");
    if (!("IntersectionObserver" in window) || reduceMotion) {
      targets.forEach(function (el) { el.classList.add("in-view"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        // stagger children with a cinematic cascade
        if (el.classList.contains("stagger")) {
          Array.prototype.forEach.call(el.children, function (child, i) {
            child.style.transitionDelay = (i * 0.09) + "s";
          });
        }
        el.classList.add("in-view");
        io.unobserve(el);
      });
    }, { threshold: 0.18, rootMargin: "0px 0px -8% 0px" });

    targets.forEach(function (el) { io.observe(el); });
  }

  /* -------- 2. Navbar state on scroll -------- */
  function initNav() {
    var nav = document.querySelector(".cine-nav");
    if (!nav) return;
    function onScroll() {
      if (window.scrollY > 40) nav.classList.add("scrolled");
      else nav.classList.remove("scrolled");
    }
    onScroll();
    return onScroll;
  }

  /* -------- 3. Parallax + scroll-linked progress (rAF loop) ----
     [data-parallax] with data-speed → translateY at differing speeds.
     [data-progress] → sets --p (0..1) as the element crosses viewport,
     used by CSS for expand-on-scroll width/radius & dolly zoom.
  -------------------------------------------------------------- */
  function initScrollEngine(navHandler) {
    var parallaxEls = Array.prototype.slice.call(document.querySelectorAll("[data-parallax]"));
    var progressEls = Array.prototype.slice.call(document.querySelectorAll("[data-progress]"));
    var hero = document.querySelector(".cine-hero");
    var heroBg = document.querySelector(".hero-layer--bg");
    var heroContent = document.querySelector(".hero-content");

    if (reduceMotion) {
      if (navHandler) window.addEventListener("scroll", navHandler, { passive: true });
      return;
    }

    var ticking = false;
    var vh = window.innerHeight;

    function update() {
      ticking = false;
      var y = window.scrollY || window.pageYOffset;

      if (navHandler) navHandler();

      // Hero dolly zoom + content drift (depth illusion)
      if (hero) {
        var hRect = hero.getBoundingClientRect();
        if (hRect.bottom > 0) {
          var hp = Math.min(Math.max(-hRect.top / vh, 0), 1); // 0 top → 1 scrolled one screen
          if (heroBg) heroBg.style.transform = "scale(" + (1.18 + hp * 0.22) + ") translate3d(0," + (hp * 60) + "px,0)";
          if (heroContent) {
            heroContent.style.transform = "translate3d(0," + (hp * 120) + "px,0)";
            heroContent.style.opacity = String(1 - hp * 1.1);
          }
        }
      }

      // Generic parallax layers — different speeds for foreground/background
      for (var i = 0; i < parallaxEls.length; i++) {
        var el = parallaxEls[i];
        var rect = el.getBoundingClientRect();
        if (rect.bottom < -200 || rect.top > vh + 200) continue;
        var speed = parseFloat(el.getAttribute("data-speed")) || 0.2;
        // distance of element center from viewport center
        var center = rect.top + rect.height / 2 - vh / 2;
        var shift = -center * speed;
        el.style.transform = "translate3d(0," + shift.toFixed(2) + "px,0)";
      }

      // Scroll-linked progress (expand-on-scroll / dolly)
      for (var j = 0; j < progressEls.length; j++) {
        var pe = progressEls[j];
        var pr = pe.getBoundingClientRect();
        // progress ramps as the element travels from entering (bottom) to centered
        var raw = (vh - pr.top) / (vh + pr.height * 0.5);
        var p = Math.min(Math.max(raw, 0), 1);
        pe.style.setProperty("--p", p.toFixed(4));
      }
    }

    function requestTick() {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }

    window.addEventListener("scroll", requestTick, { passive: true });
    window.addEventListener("resize", function () { vh = window.innerHeight; requestTick(); }, { passive: true });
    update();
  }

  function init() {
    initReveals();
    var navHandler = initNav();
    initScrollEngine(navHandler);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
