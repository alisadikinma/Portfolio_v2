// PF-2 hero scan — the single orchestrated motion moment.
// Without JS (or for crawlers) the final frame is already in the HTML.
(function () {
  var cam = document.querySelector("[data-cam]");
  if (!cam || !window.gsap) return;
  document.documentElement.classList.add("js-scan");

  var dets = cam.querySelectorAll(".det");
  var scan = cam.querySelector(".scan");
  var status = cam.querySelector("[data-status]");
  var counters = cam.querySelectorAll("[data-count]");

  function finalState() {
    gsap.set(dets, { autoAlpha: 1, scale: 1 });
    gsap.set(scan, { autoAlpha: 0 });
    counters.forEach(function (el) { el.textContent = el.dataset.count; });
    status.textContent = status.dataset.done;
  }

  var mm = gsap.matchMedia();

  mm.add(
    { motion: "(prefers-reduced-motion: no-preference)", reduce: "(prefers-reduced-motion: reduce)" },
    function (ctx) {
      if (ctx.conditions.reduce) { finalState(); return; }

      var tl = gsap.timeline({ defaults: { ease: "power2.out" }, delay: 0.35 });

      // 1. one sweep top -> bottom (transform only)
      tl.fromTo(scan, { yPercent: -100, autoAlpha: 1 }, { yPercent: 100, duration: 1.6, ease: "power1.inOut" })
        .to(scan, { autoAlpha: 0, duration: 0.3 }, ">-0.15");

      // 2. detection boxes lock on as the line passes them
      tl.fromTo(dets,
        { autoAlpha: 0, scale: 1.25 },
        { autoAlpha: 1, scale: 1, duration: 0.45, ease: "back.out(1.6)", stagger: 0.28 },
        0.45);

      // 3. HUD counters tick up in sync
      counters.forEach(function (el) {
        var o = { v: 0 };
        tl.to(o, {
          v: Number(el.dataset.count), duration: 1.4, ease: "power1.out",
          onUpdate: function () { el.textContent = Math.round(o.v); }
        }, 0.5);
      });

      tl.call(function () { status.textContent = status.dataset.done; });

      return function () { tl.kill(); };
    }
  );
})();
