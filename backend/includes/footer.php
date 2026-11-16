</main>

    </div>

</div>
<script>
    (() => {
        const toggle = document.querySelector(".menu-toggle");
        const sidebar = document.querySelector(".sidebar");

        const themeToggle = document.querySelector(".theme-toggle");
        const themeIcon = document.querySelector(".theme-toggle-icon");
        const themeText = document.querySelector(".theme-toggle-text");
        const root = document.documentElement;

        const syncThemeToggle = () => {
            if (!themeToggle || !themeIcon || !themeText) {
                return;
            }

            const isDark = root.classList.contains("dark-mode");
            themeToggle.setAttribute("aria-pressed", String(isDark));
            themeToggle.setAttribute(
                "aria-label",
                isDark ? "Switch to light mode" : "Switch to dark mode"
            );
            themeIcon.textContent = isDark ? "☀" : "☾";
            themeText.textContent = isDark ? "Light" : "Dark";
        };

        syncThemeToggle();

        // Collapsible sidebar groups. The user's choices are remembered locally,
        // while the current page's group is always opened automatically.
        const sidebarGroups = document.querySelectorAll(".sidebar-group[data-sidebar-group]");
        const sidebarStorageKey = "st-sidebar-groups";

        const readSidebarState = () => {
            try {
                const saved = JSON.parse(localStorage.getItem(sidebarStorageKey) || "{}");
                return saved && typeof saved === "object" ? saved : {};
            } catch (error) {
                return {};
            }
        };

        const writeSidebarState = (state) => {
            try {
                localStorage.setItem(sidebarStorageKey, JSON.stringify(state));
            } catch (error) {
                // Navigation remains fully usable when localStorage is unavailable.
            }
        };

        const sidebarState = readSidebarState();

        sidebarGroups.forEach((group) => {
            const button = group.querySelector(".sidebar-section-title");
            const items = group.querySelector(".sidebar-group-items");

            if (!button || !items) {
                return;
            }

            const groupKey = group.dataset.sidebarGroup;
            const hasActiveLink = Boolean(group.querySelector("a.active"));
            const savedExpanded = sidebarState[groupKey];
            const expanded = hasActiveLink || savedExpanded !== false;

            button.setAttribute("aria-expanded", String(expanded));
            group.classList.toggle("is-collapsed", !expanded);

            button.addEventListener("click", () => {
                const nextExpanded = button.getAttribute("aria-expanded") !== "true";
                button.setAttribute("aria-expanded", String(nextExpanded));
                group.classList.toggle("is-collapsed", !nextExpanded);
                sidebarState[groupKey] = nextExpanded;
                writeSidebarState(sidebarState);
            });
        });

        if (themeToggle) {
            themeToggle.addEventListener("click", async () => {
                const wasDark = root.classList.contains("dark-mode");
                const isDark = !wasDark;
                root.classList.toggle("dark-mode", isDark);

                syncThemeToggle();
                themeToggle.disabled = true;
                try {
                    const token = document.body.dataset.csrfToken || "";
                    const response = await fetch("/st/backend/auth/update_ui_mode.php", {
                        method: "POST",
                        headers: {"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"},
                        body: new URLSearchParams({csrf_token: token, mode: isDark ? "dark" : "light"}),
                        keepalive: true
                    });
                    if (!response.ok) {
                        throw new Error("Unable to save appearance mode");
                    }
                    const result = await response.json();
                    if (!result.success) {
                        throw new Error("Unable to save appearance mode");
                    }
                } catch (error) {
                    root.classList.toggle("dark-mode", wasDark);
                    syncThemeToggle();
                } finally {
                    themeToggle.disabled = false;
                }
            });
        }

        if (!toggle || !sidebar) {
            return;
        }

        const closeMenu = () => {
            document.body.classList.remove("navigation-open");
            toggle.setAttribute("aria-expanded", "false");
            toggle.setAttribute("aria-label", "Open navigation");
        };

        toggle.addEventListener("click", () => {
            const isOpen = document.body.classList.toggle("navigation-open");
            toggle.setAttribute("aria-expanded", String(isOpen));
            toggle.setAttribute(
                "aria-label",
                isOpen ? "Close navigation" : "Open navigation"
            );
        });

        sidebar.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                if (window.innerWidth <= 760) {
                    closeMenu();
                }
            });
        });

        window.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeMenu();
            }
        });

        window.addEventListener("resize", () => {
            if (window.innerWidth > 760) {
                closeMenu();
            }
        });
    })();
</script>

</body>

</html>
<?php

if (ob_get_level() > 0) {
    ob_end_flush();
}

?>
