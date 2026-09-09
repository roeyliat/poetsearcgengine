(function () {
  const root = document.querySelector(".poet-directory");
  if (!root || typeof POET_DIRECTORY === "undefined") {
    return;
  }

  const cardsEl = document.getElementById("poet-cards");
  const countEl = document.getElementById("poet-count");
  const emptyEl = document.getElementById("poet-empty");
  const qEl = document.getElementById("poet-q");
  const ageEl = document.getElementById("poet-age");
  const clearEl = document.getElementById("poet-clear");

  let therapists = [];

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
        (item.notes || ""),
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
      .replace(/"/g, "&quot;");
  }

  function telHref(phone) {
    return "tel:" + String(phone).replace(/[^\d+]/g, "");
  }

  function cardHtml(item) {
    const location = item.settlement_label || (item.online ? "טיפול מקוון" : "יישוב לא צוין");
    const funds = (item.fund_labels || []).map(function (label) {
      return "<em>" + escapeHtml(label) + "</em>";
    }).join("");
    const langs = (item.language_labels || []).map(function (label) {
      return "<em>" + escapeHtml(label) + "</em>";
    }).join("");
    const phones = (item.phones || []).map(function (phone) {
      return '<a class="poet-btn-phone" href="' + escapeHtml(telHref(phone)) + '">טלפון ' + escapeHtml(phone) + "</a>";
    }).join("");
    const emails = (item.emails || []).map(function (email) {
      return '<a class="poet-btn-mail" href="mailto:' + escapeHtml(email) + '">מייל</a>';
    }).join("");
    const notes = item.notes ? '<p class="poet-card-notes">' + escapeHtml(item.notes) + "</p>" : "";
    const age = item.age_label ? "<span>גילאים: " + escapeHtml(item.age_label) + "</span>" : "";
    const regions = (item.region_labels || []).length
      ? "<span>" + escapeHtml((item.region_labels || []).join(", ")) + "</span>"
      : "";
    const onlineTag = item.online ? "<em>מקוון</em>" : "";

    return (
      '<article class="poet-card">' +
        '<div class="poet-card-head">' +
          "<h3>" + escapeHtml(item.name) + "</h3>" +
        "</div>" +
        '<p class="poet-meta">' +
          "<span>" + escapeHtml(location) + "</span>" +
          regions +
          age +
        "</p>" +
        '<div class="poet-tags">' + funds + langs + onlineTag + "</div>" +
        notes +
        '<div class="poet-actions">' + phones + emails + "</div>" +
      "</article>"
    );
  }

  function render() {
    const visible = therapists.filter(matches);
    cardsEl.innerHTML = visible.map(cardHtml).join("");
    countEl.textContent = visible.length
      ? visible.length + " מרפאות בעיסוק מוסמכות"
      : "";
    emptyEl.hidden = visible.length > 0;
  }

  function bind() {
    ["input", "change"].forEach(function (evt) {
      root.addEventListener(evt, function (event) {
        if (event.target.closest(".poet-filters")) {
          render();
        }
      });
    });
    if (clearEl) {
      clearEl.addEventListener("click", function () {
        qEl.value = "";
        ageEl.value = "";
        root.querySelectorAll(".poet-chips input").forEach(function (input) {
          input.checked = false;
        });
        render();
      });
    }
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
      therapists = data.therapists || [];
      render();
    })
    .catch(function () {
      countEl.textContent = "";
      emptyEl.hidden = false;
      emptyEl.textContent = "לא ניתן לטעון את כלי החיפוש כרגע. נסו לרענן את העמוד.";
    });
})();
