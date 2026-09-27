import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import {
  getAuth,
  onAuthStateChanged,
  signInWithEmailAndPassword,
  signOut,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";
import {
  getFirestore,
  collection,
  doc,
  getDoc,
  getDocs,
  setDoc,
  updateDoc,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";

const firebaseConfig = {
  apiKey: "AIzaSyD0rxR7nP9WnHmHpEO094i-EIfiqFck_7c",
  authDomain: "poetsearchengine.firebaseapp.com",
  projectId: "poetsearchengine",
  storageBucket: "poetsearchengine.firebasestorage.app",
  messagingSenderId: "192182653029",
  appId: "1:192182653029:web:605de69563b1a8da0e768c",
};

const appState = {
  firebaseApp: null,
  auth: null,
  db: null,
  user: null,
  therapists: [],
  authReady: false,
  formMode: "add",
  editingTherapistId: null,
};

window.__poetAdminAppState = appState;

const elements = {
  loginScreen: document.getElementById("login-screen"),
  dashboardScreen: document.getElementById("dashboard-screen"),
  loginForm: document.getElementById("login-form"),
  emailInput: document.getElementById("email"),
  passwordInput: document.getElementById("password"),
  loginMessage: document.getElementById("login-message"),
  logoutButton: document.getElementById("logout-button"),
  addTherapistButton: document.getElementById("add-therapist-button"),
  therapistList: document.getElementById("therapist-list"),
  dashboardNotification: document.getElementById("dashboard-notification"),
  therapistModal: document.getElementById("therapist-modal"),
  therapistModalTitle: document.getElementById("therapist-modal-title"),
  closeModalButton: document.getElementById("close-modal-button"),
  therapistForm: document.getElementById("therapist-form"),
  therapistFormError: document.getElementById("therapist-form-error"),
  cancelTherapistButton: document.getElementById("cancel-therapist-button"),
  therapistName: document.getElementById("therapist-name"),
  therapistSettlementLabel: document.getElementById("therapist-settlement-label"),
  therapistSettlements: document.getElementById("therapist-settlements"),
  therapistRegions: document.getElementById("therapist-regions"),
  therapistRegionLabels: document.getElementById("therapist-region-labels"),
  therapistAgeMin: document.getElementById("therapist-age-min"),
  therapistAgeMax: document.getElementById("therapist-age-max"),
  therapistAgeLabel: document.getElementById("therapist-age-label"),
  therapistAgeOpenEnded: document.getElementById("therapist-age-open-ended"),
  therapistPhones: document.getElementById("therapist-phones"),
  therapistEmails: document.getElementById("therapist-emails"),
  therapistLanguages: document.getElementById("therapist-languages"),
  therapistLanguageLabels: document.getElementById("therapist-language-labels"),
  therapistFunds: document.getElementById("therapist-funds"),
  therapistFundLabels: document.getElementById("therapist-fund-labels"),
  therapistOnline: document.getElementById("therapist-online"),
  therapistInPerson: document.getElementById("therapist-in-person"),
};

async function initFirebaseClient() {
  const config = firebaseConfig;

  if (!config || !config.apiKey || !config.projectId || !config.appId) {
    const message = "פרטי Firebase חסרים מהקובץ ההגדרה של האתר.";
    console.error(message);
    setLoginMessage(message, true);
    throw new Error(message);
  }

  try {
    appState.firebaseApp = initializeApp(config);
    appState.auth = getAuth(appState.firebaseApp);
    appState.db = getFirestore(appState.firebaseApp);
    appState.authReady = true;
    attachAuthStateListener();
  } catch (error) {
    console.error("Firebase initialization failed:", error);
    const message = "Firebase Auth לא הוגדר כראוי. בדקו את פרטי הפרויקט.";
    setLoginMessage(message, true);
    throw error;
  }
}

function setScreen(screenName) {
  const screens = ["login-screen", "dashboard-screen"];
  screens.forEach((name) => {
    const visible = name === screenName;
    const node = document.getElementById(name);
    if (node) {
      node.classList.toggle("visible", visible);
    }
  });
}

function showLoginScreen() {
  setScreen("login-screen");
}

function showDashboardScreen() {
  setScreen("dashboard-screen");
}

function setDashboardNotification(text, isError = false) {
  if (!elements.dashboardNotification) {
    return;
  }

  elements.dashboardNotification.textContent = text || "";
  elements.dashboardNotification.classList.toggle("error", isError);
  elements.dashboardNotification.hidden = !text;
}

function setTherapistFormError(text) {
  if (!elements.therapistFormError) {
    return;
  }

  elements.therapistFormError.textContent = text || "";
  elements.therapistFormError.hidden = !text;
}

function renderTherapistList() {
  const list = appState.therapists;
  if (!Array.isArray(list) || list.length === 0) {
    elements.therapistList.innerHTML = '<div class="empty-state">לא נמצאו מטפלים.</div>';
    return;
  }

  elements.therapistList.innerHTML = list
    .map((therapist) => {
      const name = therapist.name || "ללא שם";
      const region = therapist.region || therapist.region_labels?.join(", ") || therapist.settlement_label || "לא צוין";
      const settlements = Array.isArray(therapist.settlements) && therapist.settlements.length
        ? therapist.settlements.join(", ")
        : therapist.settlement_label || "לא צוין";
      const phoneList = Array.isArray(therapist.phones) ? therapist.phones : therapist.phone ? [therapist.phone] : [];
      const emailList = Array.isArray(therapist.emails) ? therapist.emails : therapist.email ? [therapist.email] : [];
      const phone = phoneList[0] || "—";
      const email = emailList[0] || "—";
      const visibility = therapist.visible === false ? "מוסתר" : "גלוי";
      const toggleLabel = therapist.visible === false ? "הצגה" : "הסתרה";
      const therapistId = String(therapist.therapistId || therapist.id || "");

      return `
        <article class="therapist-row">
          <div class="therapist-main">
            <h3>${escapeHtml(name)}</h3>
            <p><strong>אזור:</strong> ${escapeHtml(region)}</p>
            <p><strong>יישובים:</strong> ${escapeHtml(settlements)}</p>
            <p><strong>טלפון:</strong> ${escapeHtml(phone)}</p>
            <p><strong>אימייל:</strong> ${escapeHtml(email)}</p>
          </div>
          <div class="therapist-actions">
            <span class="status-badge ${therapist.visible === false ? "hidden" : "visible"}">${escapeHtml(visibility)}</span>
            <button type="button" class="table-button" data-action="toggle-visibility" data-id="${escapeHtml(therapistId)}">${escapeHtml(toggleLabel)}</button>
            <button type="button" class="table-button" data-action="edit" data-id="${escapeHtml(therapistId)}">עריכה</button>
          </div>
        </article>
      `;
    })
    .join("");
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function setLoginMessage(text, isError = false) {
  elements.loginMessage.textContent = text;
  elements.loginMessage.classList.toggle("error", isError);
}

function getHebrewAuthErrorMessage(error) {
  const code = error && typeof error.code === "string" ? error.code : "";

  const messages = {
    "auth/invalid-email": "כתובת האימייל אינה תקינה.",
    "auth/user-disabled": "החשבון הזה הושבת.",
    "auth/user-not-found": "לא נמצא חשבון עם האימייל הזה.",
    "auth/wrong-password": "הסיסמה שגויה. יש לבדוק שוב את הנתונים.",
    "auth/too-many-requests": "יותר מדי ניסיונות התחברות. נסו שוב מאוחר יותר.",
    "auth/network-request-failed": "חיבור הרשת נכשל. בדקו את החיבור ונסו שוב.",
    "auth/invalid-credential": "פרטי ההתחברות אינם תקינים. בדקו את האימייל והסיסמה.",
    "auth/internal-error": "התחברות נכשלה עקב שגיאת מערכת. נסו שוב.",
  };

  return messages[code] || error?.message || "התחברות נכשלה. נסו שוב.";
}

async function signInWithEmailPassword(email, password) {
  if (!appState.authReady || !appState.auth) {
    throw new Error("Firebase Auth is not initialized yet.");
  }

  return signInWithEmailAndPassword(appState.auth, email, password);
}

async function checkAdminAuthorization(user) {
  if (!user || !appState.db) {
    return false;
  }

  try {
    const adminDocRef = doc(appState.db, "admins", user.uid);
    const adminDoc = await getDoc(adminDocRef);

    if (!adminDoc.exists()) {
      return false;
    }

    return !!adminDoc.data()?.active;
  } catch (error) {
    console.error("Firestore admin authorization failed:", error);
    setLoginMessage("לא ניתן לבדוק את הרשאות המנהל. נסו שוב מאוחר יותר.", true);
    return false;
  }
}

function attachAuthStateListener() {
  if (!appState.auth) {
    return;
  }

  onAuthStateChanged(appState.auth, async (user) => {
    if (!user) {
      appState.user = null;
      showLoginScreen();
      elements.loginForm.reset();
      setLoginMessage("");
      setDashboardNotification("");
      return;
    }

    try {
      const isAdmin = await checkAdminAuthorization(user);

      if (!isAdmin) {
        await signOut(appState.auth);
        appState.user = null;
        showLoginScreen();
        setLoginMessage("למשתמש זה אין הרשאת מנהל.", true);
        setDashboardNotification("");
        return;
      }

      appState.user = user;
      showDashboardScreen();
      await loadTherapistsFromFirestore();
    } catch (error) {
      console.error("Authorization check failed:", error);
      appState.user = null;
      showLoginScreen();
      setLoginMessage("לא ניתן לבדוק את הרשאות המנהל. נסו שוב מאוחר יותר.", true);
      setDashboardNotification("");
    }
  });
}

async function generateStableTherapistId(therapist) {
  const seed = JSON.stringify({
    name: therapist?.name || "",
    settlement_label: therapist?.settlement_label || "",
    settlements: Array.isArray(therapist?.settlements) ? therapist.settlements : [],
    phones: Array.isArray(therapist?.phones) ? therapist.phones : [],
    emails: Array.isArray(therapist?.emails) ? therapist.emails : [],
  });

  const bytes = new TextEncoder().encode(seed);
  const hash = await crypto.subtle.digest("SHA-256", bytes);
  return Array.from(new Uint8Array(hash))
    .map((value) => value.toString(16).padStart(2, "0"))
    .join("")
    .slice(0, 32);
}

async function loadTherapistsFromFirestore() {
  if (!appState.db) {
    appState.therapists = [];
    renderTherapistList();
    return;
  }

  try {
    const snapshot = await getDocs(collection(appState.db, "therapists"));
    appState.therapists = snapshot.docs.map((docSnapshot) => {
      const data = docSnapshot.data();
      return {
        id: docSnapshot.id,
        therapistId: data?.therapistId || docSnapshot.id,
        ...data,
      };
    });

    appState.therapists.sort((a, b) => String(a.name || "").localeCompare(String(b.name || ""), "he"));
    renderTherapistList();
  } catch (error) {
    console.error("Failed to load therapists from Firestore:", error);
    appState.therapists = [];
    elements.therapistList.innerHTML = '<div class="empty-state">לא ניתן לטעון את רשימת המטפלים מהמאגר.</div>';
    setDashboardNotification("לא ניתן לטעון את רשימת המטפלים מהמאגר.", true);
  }
}

function normalizeList(value) {
  if (Array.isArray(value)) {
    return value.map((item) => String(item).trim()).filter(Boolean);
  }

  return String(value ?? "")
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
}

function normalizeOptionalNumber(value) {
  const raw = String(value ?? "").trim();
  if (!raw) {
    return null;
  }

  const number = Number(raw);
  return Number.isFinite(number) ? number : null;
}

function getTherapistFormPayload() {
  const ageMin = normalizeOptionalNumber(elements.therapistAgeMin.value);
  const ageMax = normalizeOptionalNumber(elements.therapistAgeMax.value);

  return {
    name: String(elements.therapistName.value || "").trim(),
    settlement_label: String(elements.therapistSettlementLabel.value || "").trim() || null,
    settlements: normalizeList(elements.therapistSettlements.value),
    regions: normalizeList(elements.therapistRegions.value),
    region_labels: normalizeList(elements.therapistRegionLabels.value),
    age_min: ageMin,
    age_max: ageMax,
    age_label: String(elements.therapistAgeLabel.value || "").trim() || null,
    age_open_ended: Boolean(elements.therapistAgeOpenEnded.checked),
    phones: normalizeList(elements.therapistPhones.value),
    emails: normalizeList(elements.therapistEmails.value),
    languages: normalizeList(elements.therapistLanguages.value),
    language_labels: normalizeList(elements.therapistLanguageLabels.value),
    funds: normalizeList(elements.therapistFunds.value),
    fund_labels: normalizeList(elements.therapistFundLabels.value),
    online: Boolean(elements.therapistOnline.checked),
    in_person: Boolean(elements.therapistInPerson.checked),
    visible: appState.formMode === "edit"
      ? !!(appState.therapists.find((therapist) => String(therapist.therapistId || therapist.id) === String(appState.editingTherapistId))?.visible)
      : true,
  };
}

function clearTherapistForm() {
  elements.therapistForm.reset();
  setTherapistFormError("");
}

function openTherapistModal(mode, therapist = null) {
  appState.formMode = mode;
  appState.editingTherapistId = therapist ? (therapist.therapistId || therapist.id || null) : null;

  clearTherapistForm();

  if (mode === "edit" && therapist) {
    elements.therapistModalTitle.textContent = "עריכת מטפלת";
    elements.therapistName.value = therapist.name || "";
    elements.therapistSettlementLabel.value = therapist.settlement_label || "";
    elements.therapistSettlements.value = Array.isArray(therapist.settlements) ? therapist.settlements.join(", ") : "";
    elements.therapistRegions.value = Array.isArray(therapist.regions) ? therapist.regions.join(", ") : "";
    elements.therapistRegionLabels.value = Array.isArray(therapist.region_labels) ? therapist.region_labels.join(", ") : "";
    elements.therapistAgeMin.value = therapist.age_min ?? "";
    elements.therapistAgeMax.value = therapist.age_max ?? "";
    elements.therapistAgeLabel.value = therapist.age_label || "";
    elements.therapistAgeOpenEnded.checked = Boolean(therapist.age_open_ended);
    elements.therapistPhones.value = Array.isArray(therapist.phones) ? therapist.phones.join(", ") : "";
    elements.therapistEmails.value = Array.isArray(therapist.emails) ? therapist.emails.join(", ") : "";
    elements.therapistLanguages.value = Array.isArray(therapist.languages) ? therapist.languages.join(", ") : "";
    elements.therapistLanguageLabels.value = Array.isArray(therapist.language_labels) ? therapist.language_labels.join(", ") : "";
    elements.therapistFunds.value = Array.isArray(therapist.funds) ? therapist.funds.join(", ") : "";
    elements.therapistFundLabels.value = Array.isArray(therapist.fund_labels) ? therapist.fund_labels.join(", ") : "";
    elements.therapistOnline.checked = Boolean(therapist.online);
    elements.therapistInPerson.checked = Boolean(therapist.in_person);
  } else {
    elements.therapistModalTitle.textContent = "הוספת מטפלת";
    elements.therapistOnline.checked = false;
    elements.therapistInPerson.checked = true;
  }

  elements.therapistModal.hidden = false;
  elements.therapistModal.classList.add("visible");
  elements.therapistModal.setAttribute("aria-hidden", "false");
}

function closeTherapistModal() {
  appState.formMode = "add";
  appState.editingTherapistId = null;
  clearTherapistForm();
  elements.therapistModal.classList.remove("visible");
  elements.therapistModal.hidden = true;
  elements.therapistModal.setAttribute("aria-hidden", "true");
}

async function toggleTherapistVisibility(therapistId, visible) {
  if (!therapistId || !appState.db) {
    return;
  }

  const ref = doc(appState.db, "therapists", therapistId);
  await updateDoc(ref, { visible });
  await loadTherapistsFromFirestore();
  setDashboardNotification(visible ? "המטפלת הוצגה מחדש." : "המטפלת הוסתרה.");
}

async function handleTherapistFormSubmit(event) {
  event.preventDefault();
  setTherapistFormError("");

  try {
    const payload = getTherapistFormPayload();
    if (!payload.name) {
      throw new Error("יש להזין שם מטפל/ת.");
    }

    if (payload.age_min !== null && payload.age_max !== null && payload.age_min > payload.age_max) {
      throw new Error("גיל מינימום אינו יכול להיות גבוה מגיל מקסימום.");
    }

    if (appState.formMode === "edit") {
      if (!appState.editingTherapistId) {
        throw new Error("לא נבחר מטפל לעריכה.");
      }

      const ref = doc(appState.db, "therapists", appState.editingTherapistId);
      const { therapistId: _ignored, visible: _visibleIgnored, ...rest } = payload;
      await updateDoc(ref, {
        ...rest,
        therapistId: appState.editingTherapistId,
      });
      setDashboardNotification("השינויים נשמרו בהצלחה.");
    } else {
      const therapistId = await generateStableTherapistId(payload);
      const ref = doc(appState.db, "therapists", therapistId);
      const existingDoc = await getDoc(ref);

      if (existingDoc.exists()) {
        const duplicateMessage = "מטפל/ת עם פרטים זהים כבר קיימ/ת במאגר.";
        setTherapistFormError(duplicateMessage);
        setDashboardNotification(duplicateMessage, true);
        return;
      }

      await setDoc(ref, {
        ...payload,
        therapistId,
        visible: true,
      });
      setDashboardNotification("המטפלת נוספה בהצלחה.");
    }

    closeTherapistModal();
    await loadTherapistsFromFirestore();
  } catch (error) {
    setTherapistFormError(error?.message || "שמירת המטפלת נכשלה.");
    setDashboardNotification(error?.message || "שמירת המטפלת נכשלה.", true);
  }
}

async function handleTherapistListClick(event) {
  const editButton = event.target.closest("[data-action='edit']");
  if (editButton) {
    const therapistId = editButton.dataset.id;
    const therapist = appState.therapists.find((item) => String(item.therapistId || item.id) === String(therapistId));
    if (therapist) {
      openTherapistModal("edit", therapist);
    }
    return;
  }

  const toggleButton = event.target.closest("[data-action='toggle-visibility']");
  if (toggleButton) {
    const therapistId = toggleButton.dataset.id;
    const therapist = appState.therapists.find((item) => String(item.therapistId || item.id) === String(therapistId));
    if (!therapist || !therapistId) {
      return;
    }

    await toggleTherapistVisibility(therapistId, therapist.visible === false);
  }
}

async function handleLogin(event) {
  event.preventDefault();

  if (!appState.authReady || !appState.auth) {
    const message = "Firebase Auth עוד לא מוכן. נא לנסות שוב בעוד מספר שניות.";
    console.error(message);
    setLoginMessage(message, true);
    return;
  }

  const email = elements.emailInput.value.trim();
  const password = elements.passwordInput.value;

  if (!email || !password) {
    setLoginMessage("יש להזין אימייל וסיסמה.", true);
    return;
  }

  try {
    setLoginMessage("מתחבר...");
    await signInWithEmailAndPassword(appState.auth, email, password);
  } catch (error) {
    console.error("Firebase sign-in failed:", error);
    setLoginMessage(getHebrewAuthErrorMessage(error), true);
  }
}

async function handleLogout() {
  if (!appState.authReady || !appState.auth) {
    return;
  }

  try {
    await signOut(appState.auth);
    setDashboardNotification("");
  } catch (error) {
    console.error("Firebase sign-out failed:", error);
    setLoginMessage(error?.message || "התנתקות נכשלה.", true);
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  try {
    await initFirebaseClient();
  } catch (error) {
    console.error("Firebase initialization failed during startup:", error);
    setLoginMessage("Firebase Auth לא הוגדר כראוי. בדקו את פרטי הפרויקט.", true);
    return;
  }

  elements.loginForm.addEventListener("submit", handleLogin);
  elements.logoutButton.addEventListener("click", handleLogout);
  elements.addTherapistButton.addEventListener("click", () => openTherapistModal("add"));
  elements.closeModalButton.addEventListener("click", closeTherapistModal);
  elements.cancelTherapistButton.addEventListener("click", closeTherapistModal);
  elements.therapistModal.addEventListener("click", (event) => {
    if (event.target === elements.therapistModal) {
      closeTherapistModal();
    }
  });
  elements.therapistForm.addEventListener("submit", handleTherapistFormSubmit);
  elements.therapistList.addEventListener("click", handleTherapistListClick);
});
