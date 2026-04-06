(function () {
  class AlpToast {
    constructor() {
      this.container = this.getOrCreateContainer();
    }

    getOrCreateContainer() {
      let container = document.querySelector(".toast-container");

      if (!container) {
        container = document.createElement("div");
        container.className = "toast-container";
        document.body.appendChild(container);
      }

      return container;
    }

    getIcon(type) {
      const icons = {
        success: `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M20 6 9 17l-5-5"></path>
          </svg>
        `,
        error: `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        `,
        warning: `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M12 9v4"></path>
            <path d="M12 17h.01"></path>
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          </svg>
        `,
        info: `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M12 16v-4"></path>
            <path d="M12 8h.01"></path>
            <circle cx="12" cy="12" r="10"></circle>
          </svg>
        `,
      };

      return icons[type] || icons.info;
    }

    show({
      type = "success",
      title = "Sucesso",
      message = "",
      duration = 3500,
      closable = true,
    } = {}) {
      const toast = document.createElement("div");
      toast.className = `toast toast-${type}`;

      const safeDuration = Math.max(1000, Number(duration) || 3500);

      toast.innerHTML = `
        <div class="toast-icon">
          ${this.getIcon(type)}
        </div>

        <div class="toast-content">
          ${title ? `<div class="toast-title">${title}</div>` : ""}
          ${message ? `<div class="toast-message">${message}</div>` : ""}
        </div>

        ${
          closable
            ? `
            <button class="toast-close" type="button" aria-label="Fechar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M18 6 6 18"></path>
                <path d="m6 6 12 12"></path>
              </svg>
            </button>
          `
            : ""
        }

        <div class="toast-progress">
          <div class="toast-progress-bar" style="animation-duration: ${safeDuration}ms;"></div>
        </div>
      `;

      this.container.appendChild(toast);

      const removeToast = () => {
        if (!toast || toast.dataset.removing === "true") return;

        toast.dataset.removing = "true";
        toast.classList.add("toast-leaving");

        toast.addEventListener(
          "animationend",
          () => {
            toast.remove();
          },
          { once: true }
        );
      };

      const closeButton = toast.querySelector(".toast-close");
      if (closeButton) {
        closeButton.addEventListener("click", removeToast);
      }

      const timer = setTimeout(removeToast, safeDuration);

      toast.addEventListener("mouseenter", () => clearTimeout(timer));

      toast.addEventListener("mouseleave", () => {
        if (toast.dataset.removing === "true") return;
        setTimeout(removeToast, 1200);
      });

      return {
        element: toast,
        close: removeToast,
      };
    }

    success(message, title = "Sucesso", duration = 3500) {
      return this.show({ type: "success", title, message, duration });
    }

    error(message, title = "Erro", duration = 4000) {
      return this.show({ type: "error", title, message, duration });
    }

    warning(message, title = "Atenção", duration = 4000) {
      return this.show({ type: "warning", title, message, duration });
    }

    info(message, title = "Informação", duration = 3500) {
      return this.show({ type: "info", title, message, duration });
    }
  }

  window.AlpToast = new AlpToast();
})();