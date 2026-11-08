// Mockup-only lightbox for stage galleries (native <dialog>: focus trap + ESC for free).
(function () {
  var lb = document.getElementById("lb"), img = document.getElementById("lb-img"), cap = document.getElementById("lb-cap");
  var list = [], i = 0, title = "";
  document.querySelectorAll("[data-count-for]").forEach(function (el) {
    var n = (window.STAGES[el.dataset.countFor] || []).length;
    el.textContent = n ? n + " foto" : "";
  });
  function show() { img.src = list[i]; img.alt = title + ", foto " + (i + 1); cap.textContent = title + " · " + (i + 1) + " / " + list.length; }
  document.querySelectorAll("[data-ccount]").forEach(function (el) {
    var n = (window.CAREER[el.dataset.ccount] || []).length;
    el.textContent = n ? n + " foto" : "";
  });
  document.querySelectorAll("[data-career]").forEach(function (b) {
    b.addEventListener("click", function () {
      list = window.CAREER[b.dataset.career] || []; if (!list.length) return;
      i = 0; title = b.querySelector("strong").textContent; show(); lb.showModal();
    });
  });
  document.querySelectorAll("[data-stage]").forEach(function (b) {
    b.addEventListener("click", function () {
      list = window.STAGES[b.dataset.stage] || []; if (!list.length) return;
      i = 0; title = b.querySelector("strong").textContent; show(); lb.showModal();
    });
  });
  lb.addEventListener("click", function (e) {
    var a = e.target.dataset.lb;
    if (a === "close" || e.target === lb) lb.close();
    if (a === "next") { i = (i + 1) % list.length; show(); }
    if (a === "prev") { i = (i - 1 + list.length) % list.length; show(); }
  });
  lb.addEventListener("keydown", function (e) {
    if (e.key === "ArrowRight") { i = (i + 1) % list.length; show(); }
    if (e.key === "ArrowLeft") { i = (i - 1 + list.length) % list.length; show(); }
  });
})();
