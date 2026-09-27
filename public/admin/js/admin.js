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
  writeBatch,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js";

const AUTH_MODE = "email-password";
const EXPECTED_THERAPIST_COUNT = 380;
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
  migrationButton: document.getElementById("migration-button"),
  therapistList: document.getElementById("therapist-list"),
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
  if (elements.migrationButton) {
    elements.migrationButton.hidden = true;
    elements.migrationButton.disabled = true;
  }
}

function showDashboardScreen() {
  setScreen("dashboard-screen");
}

function updateMigrationButtonState(hasExistingData = false) {
  if (!elements.migrationButton) {
    return;
  }

  elements.migrationButton.hidden = hasExistingData;
  elements.migrationButton.disabled = hasExistingData;
}

function renderTherapistList() {
  const list = appState.therapists;
  if (!Array.isArray(list) || list.length === 0) {
    elements.therapistList.innerHTML = '<div class="empty-state">לא נמצאו מטפלים.</div>';
    return;
  }

  elements.therapistList.innerHTML = list.map((therapist) => {
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
          <button type="button" class="table-button" data-action="edit" data-id="${escapeHtml(String(therapist.id || therapist.therapistId || ""))}">עריכה</button>
        </div>
      </article>
    `;
  }).join("");
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
      return;
    }

    try {
      const isAdmin = await checkAdminAuthorization(user);

      if (!isAdmin) {
        await signOut(appState.auth);
        appState.user = null;
        showLoginScreen();
        setLoginMessage("למשתמש זה אין הרשאת מנהל.", true);
        return;
      }

      appState.user = user;
      showDashboardScreen();
      await loadTherapistsFromFirestore();
      updateMigrationButtonState(appState.therapists.length > 0);
    } catch (error) {
      console.error("Authorization check failed:", error);
      appState.user = null;
      showLoginScreen();
      setLoginMessage("לא ניתן לבדוק את הרשאות המנהל. נסו שוב מאוחר יותר.", true);
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

async function hasExistingTherapists() {
  if (!appState.db) {
    return false;
  }

  const snapshot = await getDocs(collection(appState.db, "therapists"));
  return !snapshot.empty;
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
    updateMigrationButtonState(appState.therapists.length > 0);
  } catch (error) {
    console.error("Failed to load therapists from Firestore:", error);
    appState.therapists = [];
    elements.therapistList.innerHTML = '<div class="empty-state">לא ניתן לטעון את רשימת המטפלים מהמאגר.</div>';
    updateMigrationButtonState(false);
  }
}

async function importTherapistsFromPublicJson() {
  if (!appState.auth || !appState.db) {
    setLoginMessage("המאגר לא מוכן לייבוא נתונים. נסו שוב מאוחר יותר.", true);
    return;
  }

  const migrationButton = elements.migrationButton;
  if (migrationButton) {
    migrationButton.disabled = true;
  }

  try {
    const response = await fetch("../data/therapists.json", { credentials: "same-origin" });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const payload = await response.json();
    const sourceTherapists = Array.isArray(payload?.therapists) ? payload.therapists : [];

    if (sourceTherapists.length !== EXPECTED_THERAPIST_COUNT) {
      setLoginMessage(`ייבוא נעצר: הכמות שנמצאה בקובץ (${sourceTherapists.length}) אינה תואמת ל- ${EXPECTED_THERAPIST_COUNT} רשומות צפויות.`, true);
      return;
    }

    const collectionAlreadyHasData = await hasExistingTherapists();
    if (collectionAlreadyHasData) {
      setLoginMessage("ייבוא נעצר: הנתונים כבר קיימים במאגר, ולכן לא בוצע overwrite של תכנים קיימים.", true);
      await loadTherapistsFromFirestore();
      return;
    }

    const recordsToImport = [];
    for (const therapist of sourceTherapists) {
      const therapistId = await generateStableTherapistId(therapist);
      recordsToImport.push({
        id: therapistId,
        data: {
          ...therapist,
          therapistId,
          visible: true,
        },
      });
    }

    const generatedIds = recordsToImport.map(({ id }) => id);
    if (generatedIds.length !== EXPECTED_THERAPIST_COUNT || new Set(generatedIds).size !== EXPECTED_THERAPIST_COUNT) {
      setLoginMessage("ייבוא נעצר: יצירת מזהי המטפלים לא הושלמה כראוי, או שקיימים מזהים כפולים. לא נכתבו נתונים למאגר.", true);
      return;
    }

    const batch = writeBatch(appState.db);
    for (const { id, data } of recordsToImport) {
      const ref = doc(collection(appState.db, "therapists"), id);
      batch.set(ref, data);
    }

    await batch.commit();

    const readbackSnapshot = await getDocs(collection(appState.db, "therapists"));
    if (readbackSnapshot.size !== EXPECTED_THERAPIST_COUNT) {
      setLoginMessage("ייבוא נכתב, אך האימות לאחר הקריאה מהמאגר נכשל: מספר הרשומות שהתקבל אינו 380. בדיקת אימות נכשלה.", true);
      return;
    }

    setLoginMessage(`ייבוא הושלם: ${readbackSnapshot.size} מטפלים נטענו מהמאגרים ואומתו בהצלחה.`);
    updateMigrationButtonState(true);
    await loadTherapistsFromFirestore();
  } catch (error) {
    console.error("Migration import failed:", error);
    setLoginMessage("ייבוא הנתונים נכשל. בדקו את הקובץ והמאגר ונסו שוב.", true);
  } finally {
    if (elements.migrationButton) {
      elements.migrationButton.disabled = false;
    }
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
  elements.migrationButton.addEventListener("click", importTherapistsFromPublicJson);
  elements.addTherapistButton.addEventListener("click", () => {
    setLoginMessage("הוספת מטפלת תתווסף בשלב הבא.");
  });

  elements.therapistList.addEventListener("click", (event) => {
    const actionButton = event.target.closest("[data-action='edit']");
    if (!actionButton) {
      return;
    }
    setLoginMessage("עריכת מטפלת תתווסף בשלב הבא.");
  });
});
