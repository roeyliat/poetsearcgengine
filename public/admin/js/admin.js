import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js";
import {
  getAuth,
  onAuthStateChanged,
  signInWithEmailAndPassword,
  signOut,
} from "https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js";

const AUTH_MODE = "email-password";
const firebaseConfig = {
  apiKey: "AIzaSyD0rxR7nP9WnHmHpE0094i-EIfiqFck_7c",
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
};

async function initFirebaseClient() {
  const config = firebaseConfig;

  if (!config || !config.apiKey || !config.projectId || !config.appId) {
    setLoginMessage("פרטי Firebase חסרים מהקובץ ההגדרה של האתר.", true);
    return;
  }

  appState.firebaseApp = initializeApp(config);
  appState.auth = getAuth(appState.firebaseApp);
  appState.authReady = true;
  attachAuthStateListener();
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

function renderTherapistList() {
  const list = appState.therapists;
  if (!Array.isArray(list) || list.length === 0) {
    elements.therapistList.innerHTML = '<div class="empty-state">לא נמצאו מטפלים.</div>';
    return;
  }

  elements.therapistList.innerHTML = list.map((therapist) => {
    const name = therapist.name || "ללא שם";
    const region = therapist.region || "לא צוין";
    const settlements = Array.isArray(therapist.settlements) && therapist.settlements.length ? therapist.settlements.join(", ") : "לא צוין";
    const phone = therapist.phone || "—";
    const email = therapist.email || "—";
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
          <button type="button" class="table-button" data-action="edit" data-id="${escapeHtml(String(therapist.id || ""))}">עריכה</button>
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

function attachAuthStateListener() {
  if (!appState.auth || !appState.auth.onAuthStateChanged) {
    return;
  }

  appState.auth.onAuthStateChanged((user) => {
    appState.user = user;
    if (user) {
      showDashboardScreen();
      loadTherapists();
      return;
    }

    showLoginScreen();
    elements.loginForm.reset();
    setLoginMessage("");
  });
}

async function loadTherapists() {
  appState.therapists = [
    {
      id: "placeholder-1",
      name: "דוגמת מטפלת",
      region: "מרכז",
      settlements: ["רמת גן", "גבעתיים"],
      phone: "050-0000000",
      email: "example@example.com",
      visible: true,
    },
  ];
  renderTherapistList();
}

async function handleLogin(event) {
  event.preventDefault();

  if (!appState.authReady || !appState.auth) {
    setLoginMessage("Firebase Auth עוד לא מוכן. נא לנסות שוב בעוד מספר שניות.", true);
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
    await signInWithEmailPassword(email, password);
  } catch (error) {
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
    setLoginMessage(error?.message || "התנתקות נכשלה.", true);
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  try {
    await initFirebaseClient();
  } catch (error) {
    setLoginMessage("Firebase Auth לא הוגדר כראוי. בדקו את פרטי הפרויקט.", true);
  }

  elements.loginForm.addEventListener("submit", handleLogin);
  elements.logoutButton.addEventListener("click", handleLogout);
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

  showLoginScreen();
});
