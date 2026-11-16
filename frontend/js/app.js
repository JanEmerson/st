/* SECTION: Shared Application JavaScript */
/*
 * ST Asset Management System
 * File: frontend/js/app.js
 * Purpose: Shared/client-side JavaScript behavior.
 * Debugging: Use the browser Console and Network tabs to trace events and requests.
 */
/*
 * ST Asset Management System
 * Shared front-end behavior
 *
 * Includes:
 * - Vehicle serial/plate-number label switching
 * - Lightweight local spelling suggestions
 * - Tab-to-accept spelling suggestion
 * - Native browser spellcheck on normal text fields
 */

(function () {
    "use strict";

    function updateAssetIdentifier() {
        const categorySelect = document.getElementById("category_id");
        const identifierLabel = document.getElementById("serial_number_label");
        const identifierInput = document.getElementById("serial_number");

        if (!categorySelect || !identifierLabel || !identifierInput) {
            return;
        }

        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryName = selectedOption
            ? selectedOption.textContent.trim().toLowerCase()
            : "";

        const isVehicle = categoryName === "vehicles";

        identifierLabel.textContent = isVehicle
            ? "Plate Number"
            : "Serial Number";

        identifierInput.placeholder = isVehicle
            ? "Example: ABC 1234"
            : "Example: ABC123456";

        identifierInput.setAttribute(
            "aria-label",
            isVehicle ? "Plate Number" : "Serial Number"
        );
    }

    /*
     * A small offline dictionary keeps the feature dependable on localhost.
     * Browser spellcheck remains enabled as a second layer.
     */
    const SPELLING_WORDS = Array.from(new Set([
        "a", "about", "account", "active", "added", "address", "administrator",
        "all", "amount", "and", "asset", "assets", "assigned", "assignment",
        "available", "backup", "building", "business", "cabinet", "category",
        "change", "chair", "clearance", "code", "condition", "computer",
        "cost", "created", "current", "custodian", "dashboard", "date",
        "department", "description", "desktop", "details", "donated", "email",
        "employee", "equipment", "existing", "fair", "file", "full", "good",
        "history", "image", "information", "inventory", "it", "keyboard",
        "laptop", "legacy", "login", "management", "maintenance", "monitor",
        "mouse", "motorcycle", "name", "new", "number", "office", "other",
        "password", "phone", "poor", "position", "printer", "purchase",
        "reference", "retired", "router", "serial", "settings", "source",
        "status", "stapler", "supplier", "supplies", "system", "telephone",
        "transfer", "transferred", "turnover", "user", "vehicle", "vehicles",
        "viewer", "whiteboard", "with", "write", "username", "year",
        "officer", "hr", "damaged", "truck", "van", "car", "projector",
        "ups", "desk", "filing", "sale", "sold", "return", "returned",
        "reason", "remarks", "approved", "processed", "transaction", "from",
        "to", "role", "inactive", "save", "cancel", "edit", "delete", "view",
        "print", "search", "filter", "submit", "update", "add", "close",
        "back", "next", "previous", "select", "required", "optional", "plate",
        "property", "accountability", "form", "report", "reports", "administration",
        "finance", "human", "resources", "purchasing", "procurement",
        "technology", "operations", "staff", "company", "building", "floor",
        "room", "location"
    ].map((word) => word.toLowerCase())));

    const WORD_SET = new Set(SPELLING_WORDS);

    const COMMON_CORRECTIONS = Object.freeze({
        admn: "admin",
        adminstrator: "administrator",
        admnistrator: "administrator",
        assest: "asset",
        assests: "assets",
        availble: "available",
        categroy: "category",
        catagory: "category",
        conditon: "condition",
        descripton: "description",
        departmant: "department",
        departement: "department",
        equipement: "equipment",
        exsiting: "existing",
        exsistng: "existing",
        invetory: "inventory",
        inventroy: "inventory",
        keybaord: "keyboard",
        managment: "management",
        maintenence: "maintenance",
        moniter: "monitor",
        offce: "office",
        poition: "position",
        purchse: "purchase",
        referance: "reference",
        retiered: "retired",
        serail: "serial",
        suppler: "supplier",
        suplier: "supplier",
        trasnfer: "transfer",
        transferr: "transfer",
        turnoverr: "turnover",
        vehcile: "vehicle",
        vehical: "vehicle",
        whitebord: "whiteboard"
    });

    let suggestionBox = null;
    let activeInput = null;
    let activeSuggestion = "";

    function isSpellcheckField(element) {
        if (!element || !(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement)) {
            return false;
        }

        if (element.disabled || element.readOnly) {
            return false;
        }

        if (element instanceof HTMLInputElement) {
            const type = (element.type || "text").toLowerCase();
            if (["hidden", "password", "number", "date", "datetime-local", "time", "month", "week", "file", "color", "range", "checkbox", "radio", "submit", "button", "reset"].includes(type)) {
                return false;
            }
        }

        // These fields normally contain identifiers rather than prose.
        const identifierNames = new Set([
            "asset_code", "serial_number", "purchase_number", "employee_id",
            "username", "legacy_reference"
        ]);

        if (identifierNames.has(element.name) || element.dataset.spellcheck === "false") {
            return false;
        }

        return true;
    }

    function levenshtein(a, b) {
        const rows = b.length + 1;
        const cols = a.length + 1;
        let previous = new Array(cols);
        let current = new Array(cols);

        for (let i = 0; i < cols; i++) {
            previous[i] = i;
        }

        for (let j = 1; j < rows; j++) {
            current[0] = j;
            for (let i = 1; i < cols; i++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                current[i] = Math.min(
                    current[i - 1] + 1,
                    previous[i] + 1,
                    previous[i - 1] + cost
                );
            }
            [previous, current] = [current, previous];
        }

        return previous[cols - 1];
    }

    function preserveCase(original, replacement) {
        if (original === original.toUpperCase()) {
            return replacement.toUpperCase();
        }
        if (original[0] && original[0] === original[0].toUpperCase()) {
            return replacement.charAt(0).toUpperCase() + replacement.slice(1);
        }
        return replacement;
    }

    function findSuggestion(word) {
        const normalized = word.toLowerCase();
        if (!normalized || normalized.length < 3 || WORD_SET.has(normalized)) {
            return "";
        }

        if (COMMON_CORRECTIONS[normalized]) {
            return preserveCase(word, COMMON_CORRECTIONS[normalized]);
        }

        let best = "";
        let bestDistance = Infinity;
        const maxDistance = normalized.length <= 5 ? 1 : 2;

        for (const candidate of WORD_SET) {
            if (Math.abs(candidate.length - normalized.length) > maxDistance) {
                continue;
            }

            const distance = levenshtein(normalized, candidate);
            if (distance <= maxDistance && distance < bestDistance) {
                best = candidate;
                bestDistance = distance;
            }
        }

        return best ? preserveCase(word, best) : "";
    }

    function ensureSuggestionBox() {
        if (suggestionBox) {
            return suggestionBox;
        }

        suggestionBox = document.createElement("div");
        suggestionBox.className = "spell-suggestion-box";
        suggestionBox.setAttribute("role", "status");
        suggestionBox.setAttribute("aria-live", "polite");
        suggestionBox.hidden = true;
        document.body.appendChild(suggestionBox);
        return suggestionBox;
    }

    function hideSuggestion() {
        if (!suggestionBox) {
            return;
        }
        suggestionBox.hidden = true;
        activeSuggestion = "";
        activeInput = null;
    }

    function positionSuggestion() {
        if (!suggestionBox || !activeInput || suggestionBox.hidden) {
            return;
        }

        const rect = activeInput.getBoundingClientRect();
        suggestionBox.style.left = `${window.scrollX + rect.left}px`;
        suggestionBox.style.top = `${window.scrollY + rect.bottom + 4}px`;
        suggestionBox.style.width = `${Math.max(rect.width, 220)}px`;
    }

    function showSuggestion(input, suggestion) {
        const box = ensureSuggestionBox();
        activeInput = input;
        activeSuggestion = suggestion;
        box.innerHTML = `Suggestion: <strong>${escapeHtml(suggestion)}</strong><span>Press Tab to accept</span>`;
        box.hidden = false;
        positionSuggestion();
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function getCurrentWord(input) {
        const value = input.value;
        const cursor = typeof input.selectionStart === "number" ? input.selectionStart : value.length;
        const before = value.slice(0, cursor);
        const match = before.match(/([A-Za-z][A-Za-z'-]*)$/);

        if (!match) {
            return null;
        }

        return {
            word: match[1],
            start: cursor - match[1].length,
            end: cursor
        };
    }

    function acceptSuggestion() {
        if (!activeInput || !activeSuggestion) {
            return false;
        }

        const current = getCurrentWord(activeInput);
        if (!current) {
            hideSuggestion();
            return false;
        }

        const before = activeInput.value.slice(0, current.start);
        const after = activeInput.value.slice(current.end);
        activeInput.value = before + activeSuggestion + after;

        const newCursor = current.start + activeSuggestion.length;
        try {
            activeInput.setSelectionRange(newCursor, newCursor);
        } catch (error) {
            // Some input types do not support selection ranges.
        }

        activeInput.dispatchEvent(new Event("input", { bubbles: true }));
        hideSuggestion();
        return true;
    }

    function initSearchableSelects() {
        document.querySelectorAll("select[data-searchable-select]").forEach((select) => {
            if (select.dataset.searchableReady === "true" || select.disabled) return;

            select.dataset.searchableReady = "true";
            const wrapper = document.createElement("div");
            const control = document.createElement("div");
            const search = document.createElement("input");
            const toggle = document.createElement("button");
            const dropdown = document.createElement("div");
            const listbox = document.createElement("div");
            const label = select.labels && select.labels[0]
                ? select.labels[0].textContent.replace("*", "").trim()
                : "options";
            const listboxId = `${select.id}_options`;
            const isRequired = select.required;
            const options = Array.from(select.options).filter((option) => option.value !== "" && !option.disabled);
            let filteredOptions = [];
            let activeIndex = -1;

            wrapper.className = "searchable-select";
            control.className = "searchable-select-control";
            dropdown.className = "searchable-select-dropdown";
            dropdown.hidden = true;
            listbox.id = listboxId;
            listbox.setAttribute("role", "listbox");

            search.type = "search";
            search.id = `${select.id}_combobox`;
            search.className = "searchable-select-input";
            search.placeholder = select.dataset.searchPlaceholder || `Search ${label.toLowerCase()}...`;
            search.setAttribute("role", "combobox");
            search.setAttribute("aria-label", label);
            search.setAttribute("aria-autocomplete", "list");
            search.setAttribute("aria-expanded", "false");
            search.setAttribute("aria-controls", listboxId);
            search.setAttribute("autocomplete", "off");
            search.setAttribute("spellcheck", "false");
            search.dataset.spellcheck = "false";
            search.required = isRequired;

            toggle.type = "button";
            toggle.className = "searchable-select-toggle";
            toggle.setAttribute("aria-label", `Show ${label.toLowerCase()} options`);
            toggle.setAttribute("aria-controls", listboxId);
            toggle.setAttribute("aria-expanded", "false");
            toggle.innerHTML = '<span aria-hidden="true">&#9662;</span>';

            const selectedOption = select.selectedOptions[0];
            search.value = select.value && selectedOption ? selectedOption.textContent.trim() : "";
            select.required = false;
            select.hidden = true;

            if (select.labels && select.labels[0]) {
                select.labels[0].htmlFor = search.id;
            }

            control.append(search, toggle);
            dropdown.appendChild(listbox);
            select.parentNode.insertBefore(wrapper, select);
            wrapper.append(control, dropdown, select);

            function setOpen(open) {
                dropdown.hidden = !open;
                search.setAttribute("aria-expanded", open ? "true" : "false");
                toggle.setAttribute("aria-expanded", open ? "true" : "false");
                toggle.setAttribute("aria-label", `${open ? "Hide" : "Show"} ${label.toLowerCase()} options`);
                if (!open) {
                    activeIndex = -1;
                    search.removeAttribute("aria-activedescendant");
                }
            }

            function setActive(index) {
                if (!filteredOptions.length) return;
                activeIndex = (index + filteredOptions.length) % filteredOptions.length;
                const activeOption = listbox.children[activeIndex];
                search.setAttribute("aria-activedescendant", activeOption.id);
                activeOption.scrollIntoView({ block: "nearest" });
            }

            function chooseOption(option) {
                select.value = option.value;
                search.value = option.textContent.trim();
                search.setCustomValidity("");
                setOpen(false);
                select.dispatchEvent(new Event("change", { bubbles: true }));
            }

            function renderOptions(query = "") {
                const normalizedQuery = query.trim().toLocaleLowerCase();
                filteredOptions = options.filter((option) =>
                    option.textContent.toLocaleLowerCase().includes(normalizedQuery)
                );
                listbox.replaceChildren();
                activeIndex = -1;

                if (!filteredOptions.length) {
                    const empty = document.createElement("div");
                    empty.className = "searchable-select-empty";
                    empty.textContent = "No matching options";
                    empty.setAttribute("role", "status");
                    listbox.appendChild(empty);
                } else {
                    filteredOptions.forEach((option, index) => {
                        const item = document.createElement("button");
                        item.type = "button";
                        item.className = "searchable-select-option";
                        item.id = `${listboxId}_option_${index}`;
                        item.setAttribute("role", "option");
                        item.setAttribute("aria-selected", option.value === select.value ? "true" : "false");
                        item.textContent = option.textContent.trim();
                        item.addEventListener("mousedown", (event) => event.preventDefault());
                        item.addEventListener("click", () => chooseOption(option));
                        listbox.appendChild(item);
                    });
                }

                setOpen(true);
            }

            search.addEventListener("input", () => {
                select.value = "";
                search.setCustomValidity(isRequired ? "Select an option from the list." : "");
                renderOptions(search.value);
            });
            search.addEventListener("focus", () => {
                if (select.value) search.select();
                renderOptions(select.value ? "" : search.value);
            });
            search.addEventListener("keydown", (event) => {
                if (event.key === "ArrowDown") {
                    event.preventDefault();
                    if (dropdown.hidden) renderOptions(search.value);
                    setActive(activeIndex < 0 ? 0 : activeIndex + 1);
                } else if (event.key === "ArrowUp") {
                    event.preventDefault();
                    if (dropdown.hidden) renderOptions(search.value);
                    setActive(activeIndex <= 0 ? filteredOptions.length - 1 : activeIndex - 1);
                } else if (event.key === "Enter" && !dropdown.hidden && activeIndex >= 0) {
                    event.preventDefault();
                    chooseOption(filteredOptions[activeIndex]);
                } else if (event.key === "Escape") {
                    setOpen(false);
                }
            });
            toggle.addEventListener("click", () => {
                if (dropdown.hidden) {
                    renderOptions(select.value ? "" : search.value);
                    search.focus();
                } else {
                    setOpen(false);
                }
            });
            document.addEventListener("pointerdown", (event) => {
                if (!wrapper.contains(event.target)) setOpen(false);
            });
            select.addEventListener("change", () => {
                const current = select.selectedOptions[0];
                if (select.value && current) {
                    search.value = current.textContent.trim();
                    search.setCustomValidity("");
                }
            });
            if (select.form) {
                select.form.addEventListener("reset", () => {
                    window.setTimeout(() => {
                        const resetOption = select.selectedOptions[0];
                        search.value = select.value && resetOption ? resetOption.textContent.trim() : "";
                        search.setCustomValidity("");
                        setOpen(false);
                    }, 0);
                });
            }
        });
    }

    function inspectForSuggestion(input) {
        if (!isSpellcheckField(input)) {
            hideSuggestion();
            return;
        }

        const current = getCurrentWord(input);
        if (!current || current.word.length < 3) {
            hideSuggestion();
            return;
        }

        const suggestion = findSuggestion(current.word);
        if (suggestion && suggestion.toLowerCase() !== current.word.toLowerCase()) {
            showSuggestion(input, suggestion);
        } else {
            hideSuggestion();
        }
    }

    function initSpellingSupport() {
        document.querySelectorAll("input[type='text'], input:not([type]), textarea").forEach((field) => {
            if (!field.hasAttribute("spellcheck")) {
                field.setAttribute("spellcheck", isSpellcheckField(field) ? "true" : "false");
            }
        });

        document.addEventListener("input", (event) => {
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
                inspectForSuggestion(event.target);
            }
        });

        document.addEventListener("focusin", (event) => {
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
                inspectForSuggestion(event.target);
            }
        });

        document.addEventListener("keydown", (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement)) return;
            if (event.key === "Tab" && activeInput === target && activeSuggestion) acceptSuggestion();
            if (event.key === "Escape") hideSuggestion();
        });

        document.addEventListener("focusout", () => {
            window.setTimeout(() => {
                if (document.activeElement !== activeInput) hideSuggestion();
            }, 100);
        });

        window.addEventListener("scroll", positionSuggestion, true);
        window.addEventListener("resize", positionSuggestion);
    }

    function init() {
        const categorySelect = document.getElementById("category_id");

        if (categorySelect) {
            categorySelect.addEventListener("change", updateAssetIdentifier);
            updateAssetIdentifier();
        }

        initSearchableSelects();
        initAssetCameraControls();
        initSpellingSupport();
    }

    function initAssetCameraControls() {
        document.querySelectorAll("[data-file-input]").forEach((button) => {
            const input = document.getElementById(button.dataset.fileInput);
            const status = document.getElementById(button.dataset.fileStatus);
            if (!input) return;

            button.addEventListener("click", () => input.click());
            input.addEventListener("change", () => {
                const fileCount = (input.files || []).length;
                if (status) {
                    status.textContent = fileCount
                        ? `${fileCount} image${fileCount === 1 ? "" : "s"} selected.`
                        : "No images selected.";
                }
            });
        });

        document.querySelectorAll("[data-camera-input]").forEach((button) => {
            const input = document.getElementById(button.dataset.cameraInput);
            const status = document.getElementById(button.dataset.cameraStatus);
            if (!input) return;

            const capturedFiles = [];
            button.addEventListener("click", () => input.click());
            input.addEventListener("change", () => {
                const selectedFiles = Array.from(input.files || []);
                if (!selectedFiles.length) return;

                let photoCount = selectedFiles.length;
                if (typeof DataTransfer === "function") {
                    capturedFiles.push(...selectedFiles);
                    const transfer = new DataTransfer();
                    capturedFiles.forEach((file) => transfer.items.add(file));
                    input.files = transfer.files;
                    photoCount = capturedFiles.length;
                }

                if (status) {
                    status.textContent = `${photoCount} camera photo${photoCount === 1 ? "" : "s"} ready to upload.`;
                }
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();

/* =========================================================
 * ST UX: COLLAPSIBLE SEARCH & FILTER PANELS
 * ---------------------------------------------------------
 * Keeps list pages visually simple while preserving all
 * existing GET-based filtering. The open/closed state is
 * remembered per page for the current browser session.
 * ========================================================= */
(function () {
    "use strict";

    function initCollapsibleFilters() {
        const panels = document.querySelectorAll(".filter-container");
        if (!panels.length) return;

        panels.forEach((panel, index) => {
            if (panel.dataset.collapsibleReady === "true") return;

            const hasExportExcel = !!panel.querySelector(
                ".export-options-trigger, .export-options-popover, .export-button, [title*='Excel'], button[title*='Excel'], .btn-export-excel"
            );

            if (!hasExportExcel) {
                panel.dataset.collapsibleReady = "true";
                return;
            }

            panel.dataset.collapsibleReady = "true";

            const children = Array.from(panel.children);
            if (!children.length) return;

            const path = window.location.pathname.replace(/[^a-z0-9]+/gi, "_");
            const storageKey = "st_filter_panel_" + path + "_" + index;
            const hasActiveFilters = Array.from(panel.querySelectorAll("input, select")).some((field) => {
                if (field.type === "submit" || field.type === "button" || field.type === "hidden") return false;
                return String(field.value || "").trim() !== "";
            });

            const body = document.createElement("div");
            body.className = "filter-collapsible-body";
            children.forEach((child) => body.appendChild(child));

            const header = document.createElement("div");
            header.className = "filter-panel-header";

            const titleWrap = document.createElement("div");
            titleWrap.className = "filter-panel-title-wrap";

            const title = document.createElement("strong");
            title.className = "filter-panel-title";
            title.textContent = "Search & Filters";

            const badge = document.createElement("span");
            badge.className = "filter-active-badge";
            badge.textContent = "Filters active";
            badge.hidden = !hasActiveFilters;

            titleWrap.appendChild(title);
            titleWrap.appendChild(badge);

            const toggle = document.createElement("button");
            toggle.type = "button";
            toggle.className = "filter-panel-toggle";
            toggle.setAttribute("aria-expanded", "false");
            toggle.innerHTML = '<span class="filter-panel-toggle-label">Show filters</span><span class="filter-panel-chevron" aria-hidden="true">⌄</span>';

            header.appendChild(titleWrap);
            header.appendChild(toggle);
            panel.appendChild(header);
            panel.appendChild(body);

            let stored = null;
            try {
                stored = sessionStorage.getItem(storageKey);
            } catch (error) {
                stored = null;
            }

            const shouldOpen = stored === "open" || (stored === null && hasActiveFilters);

            function setOpen(open, persist = true) {
                panel.classList.toggle("is-open", open);
                toggle.setAttribute("aria-expanded", open ? "true" : "false");
                toggle.querySelector(".filter-panel-toggle-label").textContent = open ? "Hide filters" : "Show filters";
                if (persist) {
                    try {
                        sessionStorage.setItem(storageKey, open ? "open" : "closed");
                    } catch (error) {
                        // Storage may be unavailable in privacy-restricted browsers.
                    }
                }
            }

            toggle.addEventListener("click", () => setOpen(!panel.classList.contains("is-open")));
            setOpen(shouldOpen, false);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initCollapsibleFilters);
    } else {
        initCollapsibleFilters();
    }
})();

