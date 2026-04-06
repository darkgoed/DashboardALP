(function () {
  console.log("[ALP] Bootstrap iniciado");
  const bootstrapScript = document.currentScript;

  function getAssetUrl(fileName) {
    if (!bootstrapScript || !bootstrapScript.src) {
      return `./${fileName}`;
    }

    return new URL(fileName, bootstrapScript.src).toString();
  }

  /* =========================
     LOAD SCRIPTS DINÂMICOS
  ========================= */

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = src;
      script.defer = true;

      script.onload = resolve;
      script.onerror = reject;

      document.head.appendChild(script);
    });
  }

  function initNavConfig() {
    const toggles = document.querySelectorAll("[data-nav-toggle]");

    toggles.forEach((toggle) => {
      toggle.addEventListener("click", function () {
        const group = toggle.closest(".nav-group");
        if (!group) return;

        group.classList.toggle("open");

        const expanded = group.classList.contains("open");
        toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
      });
    });
  }

  async function init() {
    try {
      await loadScript("https://unpkg.com/lucide@latest");

      if (window.lucide) {
        window.lucide.createIcons();
      }
    } catch (e) {
      console.error("[ALP] Falha ao carregar Lucide:", e);
    }

    try {
      await loadScript(getAssetUrl("toast.js"));
    } catch (e) {
      console.error("[ALP] Falha ao carregar toast.js:", e);
    }

    initNavConfig();
    initFlashToast();
  }

  /* =========================
     TOAST GLOBAL (FLASH)
  ========================= */

  function initFlashToast() {
    const flash = window.__ALP_FLASH__ || null;

    if (!flash || !Array.isArray(flash)) return;

    flash.forEach((item) => {
      const type = item.type || "info";
      const message = item.message || "";

      if (window.AlpToast && typeof AlpToast[type] === "function") {
        AlpToast[type](message);
      }
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
