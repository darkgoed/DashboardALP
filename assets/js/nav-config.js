document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('[data-nav-toggle]');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const group = toggle.closest('.nav-group');
            if (!group) return;

            group.classList.toggle('open');

            const expanded = group.classList.contains('open');
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    });
});
