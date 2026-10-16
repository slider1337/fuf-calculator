// Application startup; feature definitions are loaded before initialization.
async function bootstrapPage() {
  if (window.bootstrap && window.bootstrap.Modal) {
    state.settingsModal = new window.bootstrap.Modal(byId("settings-modal"));
    state.inviteModal = new window.bootstrap.Modal(byId("invite-modal"));
    state.usersModal = new window.bootstrap.Modal(byId("users-modal"));
  } else {
    throw new Error("Bootstrap Modal konnte nicht initialisiert werden.");
  }

  await loadCurrentUser();
  await loadSettings();
  await loadTripList();
  window.addEventListener("popstate", async () => {
    if (state.currentRoutePath !== window.location.pathname && !allowManagementNavigation()) { window.history.pushState({}, "", state.currentRoutePath); return; }
    try {
      await renderCurrentRoute({ reuseLoadedTrip: true });
    } catch (error) {
      alert("Route konnte nicht geladen werden: " + error.message);
    }
  });

  observeHeadScroll();
  observeSheetSwipe();
  await renderCurrentRoute();
}

// --- Event Handlers ---

let appInitialized = false;
function initializeApp() {
  if (appInitialized) return;
  appInitialized = true;
  initCalculator();
  initNavigation();
  initAdministration();
  initTripEditor();
  initTrips();
  initRegistrations();
  initSettlement();
  bootstrapPage().catch((error) => {
    byId("trip-list-empty").classList.remove("d-none");
    byId("trip-list-empty").textContent = "Fehler beim Laden: " + error.message;
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initializeApp, {once: true});
} else {
  initializeApp();
}
