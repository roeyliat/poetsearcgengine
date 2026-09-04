(function () {
  const root = document.querySelector(".poet-directory");
  if (!root || typeof POET_DIRECTORY === "undefined") {
    return;
  }

  const PAGE_SIZE = 24;
  const cardsEl = document.getElementById("poet-cards");
  const countEl = document.getElementById("poet-count");
  const emptyEl = document.getElementById("poet-empty");
  const qEl = document.getElementById("poet-q");
  const ageEl = document.getElementById("poet-age");
  const filtersEl = document.getElementById("poet-filters");
  const loadMoreEl = document.getElementById("poet-load-more");

  let therapists = [];
  let visibleLimit = PAGE_SIZE;
  let inputTimer;

  function checkedValues(group) {
    return Array.from(root.querySelectorAll('[data-filter="' + group + '"] input:checked')).map(
      function (input) {
        return input.value;
      }
    );
  }

  function matches(item) {
    const q = (qEl.value || "").trim().toLowerCase();
    if (q) {
      const hay = [
        item.name,
        item.settlement_label,
        (item.settlements || []).join(" "),
        (item.fund_labels || []).join(" "),
      ]
        .join(" ")
        .toLowerCase();
      if (hay.indexOf(q) === -1) {
        return false;
      }
    }

    const ageRaw = ageEl.value;
    if (ageRaw !== "") {
      const age = Number(ageRaw);
      if (!Number.isNaN(age)) {
        const min = item.age_min;
        const max = item.age_max;
        if (min != null && age < Number(min)) {
          return false;
        }
        if (!item.age_open_ended && max != null && age > Number(max)) {
          return false;
        }
      }
    }

    const regions = checkedValues("region");
    if (regions.length && !regions.some(function (slug) { return (item.regions || []).indexOf(slug) !== -1; })) {
      return false;
    }

    const funds = checkedValues("fund");
    if (funds.length && !funds.some(function (slug) { return (item.funds || []).indexOf(slug) !== -1; })) {
      return false;
    }

    const languages = checkedValues("language");
    if (languages.length && !languages.some(function (slug) { return (item.languages || []).indexOf(slug) !== -1; })) {
      return false;
    }

    const modalities = checkedValues("modality");
    if (modalities.length) {
      const ok = modalities.some(function (slug) {
        if (slug === "online") {
          return !!item.online;
        }
        if (slug === "in_person") {
          return !!item.in_person;
        }
        return false;
      });
      if (!ok) {
        return false;
      }
    }

    return true;
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function telHref(phone) {
    return "tel:" + String(phone).replace(/[^\d+]/g, "");
  }

  function cardHtml(item, index) {
    const location = item.settlement_label || (item.online ? "טיפול מקוון" : "יישוב לא צוין");
    const cardId = "poet-card-" + index;
    const funds = (item.fund_labels || []).map(function (label) {
      return "<em>" + escapeHtml(label) + "</em>";
    }).join("");
    const langs = (item.language_labels || []).map(function (label) {
      return "<em>" + escapeHtml(label) + "</em>";
    }).join("");
    const phones = (item.phones || []).map(function (phone) {
      return (
        '<a class="poet-btn-phone" href="' + escapeHtml(telHref(phone)) +
        '" aria-label="התקשרות אל ' + escapeHtml(item.name) + ', ' + escapeHtml(phone) +
        '">טלפון ' + escapeHtml(phone) + "</a>"
      );
    }).join("");
    const emails = (item.emails || []).map(function (email) {
      return (
        '<a class="poet-btn-mail" href="mailto:' + escapeHtml(email) +
        '" aria-label="שליחת מייל אל ' + escapeHtml(item.name) + '">מייל</a>'
      );
    }).join("");
    const age = item.age_label ? "<span>גילאים: " + escapeHtml(item.age_label) + "</span>" : "";
    const regions = (item.region_labels || []).length
      ? "<span>" + escapeHtml((item.region_labels || []).join(", ")) + "</span>"
      : "";
    const onlineTag = item.online ? '<em class="poet-online-tag">טיפול מקוון</em>' : "";

    return (
      '<article class="poet-card" aria-labelledby="' + cardId + '">' +
        '<div class="poet-card-head">' +
          '<h3 id="' + cardId + '">' + escapeHtml(item.name) + "</h3>" +
          '<span class="poet-badge">מוסמכת POET</span>' +
        "</div>" +
        '<p class="poet-meta">' +
          '<span class="poet-meta-location">' + escapeHtml(location) + "</span>" +
          regions +
          age +
        "</p>" +
        '<div class="poet-tags">' + funds + langs + onlineTag + "</div>" +
        '<div class="poet-actions">' + phones + emails + "</div>" +
      "</article>"
    );
  }

  function render() {
    const matched = therapists.filter(matches);
    const rendered = matched.slice(0, visibleLimit);

    cardsEl.innerHTML = rendered.map(cardHtml).join("");
    cardsEl.setAttribute("aria-busy", "false");
    countEl.textContent = matched.length
      ? "נמצאו " + matched.length + " מרפאות בעיסוק מוסמכות · מוצגות " + rendered.length
      : "לא נמצאו תוצאות";
    emptyEl.hidden = matched.length > 0;
    loadMoreEl.hidden = rendered.length >= matched.length;
  }

  function bind() {
    filtersEl.addEventListener("input", function () {
      window.clearTimeout(inputTimer);
      inputTimer = window.setTimeout(function () {
        visibleLimit = PAGE_SIZE;
        render();
      }, 100);
    });

    filtersEl.addEventListener("change", function () {
      visibleLimit = PAGE_SIZE;
      render();
    });

    filtersEl.addEventListener("reset", function () {
      window.setTimeout(function () {
        visibleLimit = PAGE_SIZE;
        render();
        qEl.focus();
      }, 0);
    });

    loadMoreEl.addEventListener("click", function () {
      visibleLimit += PAGE_SIZE;
      render();
      const firstNewCard = cardsEl.children[visibleLimit - PAGE_SIZE];
      if (firstNewCard) {
        firstNewCard.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }
    });

  }

  bind();
  countEl.textContent = "טוען את כלי החיפוש…";

  fetch(POET_DIRECTORY.endpoint, { credentials: "same-origin" })
    .then(function (res) {
      if (!res.ok) {
        throw new Error("HTTP " + res.status);
      }
      return res.json();
    })
    .then(function (data) {
      therapists = data.therapists || data || [];
      if (!Array.isArray(therapists)) {
        therapists = [];
      }
      therapists.sort(function (a, b) {
        return String(a.name || "").localeCompare(String(b.name || ""), "he");
      });
      render();
    })
    .catch(function () {
      cardsEl.setAttribute("aria-busy", "false");
      countEl.textContent = "";
      emptyEl.hidden = false;
      emptyEl.textContent = "לא ניתן לטעון את כלי החיפוש כרגע. נסו לרענן את העמוד.";
    });
})();
