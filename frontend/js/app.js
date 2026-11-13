/* Smart Waste — Portal Warga (Website / User) */
const API =
  window.SMART_WASTE_API ||
  new URL("../backend-php/index.php", document.baseURI).toString();
const TOKEN_KEY = "smartwaste_user_token";
const state = {
  user: null,
  reports: [],
  filter: "all",
  poll: null,
  loadError: "",
  hasLoadedData: false,
};
const reportPhotoState = { file: null, previewUrl: "" };

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function token() {
  return localStorage.getItem(TOKEN_KEY) || "";
}

function setConnectionStatus(status) {
  const pill = $("#connection-status");
  if (!pill) return;
  pill.dataset.state = status;
  $("#connection-label").textContent = {
    checking: "Menghubungkan",
    connected: "Terhubung",
    offline: "Belum terhubung",
  }[status] || "Terhubung";
}

function updateConnectionWarning() {
  const warning = $("#connection-warning");
  if (!warning) return;
  warning.hidden = !state.loadError;
  if (!state.loadError) return;
  $("#connection-warning-copy").textContent = state.hasLoadedData
    ? "Data terakhir masih ditampilkan, tetapi belum berhasil diperbarui."
    : "Data belum berhasil dimuat. Periksa koneksi server lalu coba lagi.";
}

async function api(method, path, payload = null, isForm = false) {
  const options = { method, headers: {} };
  const currentToken = token();
  if (currentToken) options.headers.Authorization = `Bearer ${currentToken}`;
  if (payload !== null) {
    if (isForm) {
      options.body = payload;
    } else {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(payload);
    }
  }
  let response;
  try {
    response = await fetch(`${API}${path}`, options);
  } catch (error) {
    setConnectionStatus("offline");
    throw new Error(
      "API belum bisa dihubungi. Pastikan Apache dan MySQL XAMPP aktif.",
    );
  }
  setConnectionStatus("connected");
  let result = {};
  try {
    result = await response.json();
  } catch (_) {
    result = {};
  }
  if (!response.ok) {
    const error = new Error(result.message || "Permintaan tidak dapat diproses.");
    error.status = response.status;
    throw error;
  }
  return result;
}

function escapeHtml(value) {
  return String(value ?? "").replace(
    /[&<>"']/g,
    (char) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      })[char],
  );
}

function safeUrl(value) {
  const url = String(value || "");
  return /^https?:\/\//i.test(url) ? url : "";
}

function setMessage(id, message = "", type = "error") {
  const element = document.getElementById(id);
  if (!element) return;
  element.textContent = message;
  element.className = `form-message ${message ? `is-${type}` : ""}`;
}

let toastTimer;
function toast(message, type = "success") {
  const element = $("#toast");
  if (!element) return;
  element.textContent = message;
  element.className = `toast is-visible is-${type}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => element.classList.remove("is-visible"), 3200);
}

function showAuth(view = "login", message = "", type = "error") {
  $("#auth-screen").hidden = false;
  $("#app-shell").hidden = true;
  $("#login-form").hidden = view !== "login";
  $("#register-form").hidden = view !== "register";
  $("#show-login").classList.toggle("is-active", view === "login");
  $("#show-register").classList.toggle("is-active", view === "register");
  $("#retry-session").hidden = true;
  $("#auth-title").textContent =
    view === "login" ? "Masuk ke Portal Warga" : "Daftar akun warga";
  $("#auth-subtitle").textContent =
    view === "login"
      ? "Pantau laporan kebersihan di sekitarmu."
      : "Buat akun untuk mengirim laporan dan memantau penanganannya.";
  setMessage(
    view === "login" ? "login-message" : "register-message",
    message,
    type,
  );
}

function showApp() {
  $("#auth-screen").hidden = true;
  $("#app-shell").hidden = false;
  renderUser();
  goPage("dashboard");
}

function renderUser() {
  const user = state.user || {};
  const name = user.name || user.nama || "User";
  const initial = name.trim().charAt(0).toUpperCase() || "U";
  const shortName = name.trim().split(/\s+/).slice(0, 2).join(" ");
  $("#user-initial").textContent = initial;
  $("#user-short-name").textContent = shortName;
  $("#hero-name").textContent = name.split(/\s+/)[0];
  $("#profile-initial").textContent = initial;
  $("#profile-display-name").textContent = name;
  $("#profile-display-username").textContent = `@${user.username || "user"}`;
  $("#profile-name").value = name;
  $("#profile-email").value = user.email || "";
  $("#profile-username").value = user.username || "";
}

function goPage(page, { scroll = true } = {}) {
  $$(".page").forEach((element) =>
    element.classList.toggle("is-active", element.id === `page-${page}`),
  );
  $$("[data-page-link]").forEach((element) =>
    element.classList.toggle("is-active", element.dataset.pageLink === page),
  );
  document.body.classList.remove("mobile-nav-open");
  if (scroll) window.scrollTo({ top: 0, behavior: "smooth" });
}

function chooseReportPhoto(file, input) {
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    input.value = "";
    setMessage("report-message", "Ukuran foto maksimal 5 MB. Pilih foto yang lebih kecil.", "error");
    return;
  }
  if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
    input.value = "";
    setMessage("report-message", "Pilih foto berformat JPG, PNG, atau WEBP.", "error");
    return;
  }
  if (reportPhotoState.previewUrl) URL.revokeObjectURL(reportPhotoState.previewUrl);
  reportPhotoState.file = file;
  reportPhotoState.previewUrl = URL.createObjectURL(file);
  $("#photo-preview-image").src = reportPhotoState.previewUrl;
  $("#photo-preview-name").textContent = file.name;
  $("#photo-name").hidden = true;
  $("#photo-preview").hidden = false;
  setMessage("report-message", "");
}

function clearReportPhoto() {
  if (reportPhotoState.previewUrl) URL.revokeObjectURL(reportPhotoState.previewUrl);
  reportPhotoState.file = null;
  reportPhotoState.previewUrl = "";
  $("#report-photo-camera").value = "";
  $("#report-photo-gallery").value = "";
  $("#photo-preview-image").removeAttribute("src");
  $("#photo-preview-name").textContent = "";
  $("#photo-preview").hidden = true;
  $("#photo-name").hidden = false;
  $("#photo-name").textContent = "Belum ada foto dipilih";
  setMessage("report-message", "");
}

function statusLabel(status) {
  return (
    {
      menunggu_verifikasi: "Menunggu Verifikasi",
      diproses: "Diproses",
      ditugaskan: "Ditugaskan",
      diangkut: "Diangkut",
      selesai: "Selesai",
      ditolak: "Ditolak",
    }[status] ||
    status ||
    "Belum ada status"
  );
}

function compactReportTimeline(entries) {
  const stageByStatus = {
    menunggu_verifikasi: { key: "received", order: 0, label: "Laporan diterima" },
    diproses: { key: "assigned", order: 1, label: "Petugas ditugaskan" },
    ditugaskan: { key: "assigned", order: 1, label: "Petugas ditugaskan" },
    diangkut: { key: "transported", order: 2, label: "Pengangkutan ke TPA" },
    selesai: { key: "completed", order: 3, label: "Selesai di TPA" },
    ditolak: { key: "rejected", order: 4, label: "Laporan ditolak" },
  };
  const stages = new Map();
  entries.forEach((item) => {
    const stage = stageByStatus[item.status];
    if (stage) stages.set(stage.key, { ...item, ...stage });
  });
  return [...stages.values()].sort((a, b) => a.order - b.order);
}

function formatDate(value, withTime = false) {
  if (!value) return "-";
  const raw = String(value).replace(" ", "T");
  const date = new Date(raw.length === 10 ? `${raw}T00:00:00` : raw);
  if (Number.isNaN(date.getTime())) return String(value);
  return new Intl.DateTimeFormat(
    "id-ID",
    withTime
      ? {
          day: "numeric",
          month: "short",
          year: "numeric",
          hour: "2-digit",
          minute: "2-digit",
        }
      : { day: "numeric", month: "short", year: "numeric" },
  ).format(date);
}

function localDateInputValue(date = new Date()) {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
}

function statusBadge(report) {
  const status = escapeHtml(report.status || "menunggu_verifikasi");
  return `<span class="status-badge status-${status}"><i></i>${escapeHtml(report.status_label || statusLabel(report.status))}</span>`;
}

function reportCard(report, compact = false) {
  const photo = safeUrl(report.photo || report.foto);
  const thumb = photo
    ? `<img src="${escapeHtml(photo)}" alt="Kondisi awal dari foto warga" loading="lazy">`
    : `<span class="report-placeholder">♻</span>`;
  return `<button class="report-item ${compact ? "is-compact" : ""}" type="button" data-report-id="${Number(report.id) || 0}">
    <span class="report-thumb">${thumb}</span>
    <span class="report-main"><strong>${escapeHtml(report.code || report.kode_laporan || "Laporan")}</strong><span>${escapeHtml(report.type || report.jenis || "Sampah")} · ${escapeHtml(report.location || report.lokasi || "Lokasi belum diisi")}</span><small>${formatDate(report.report_date || report.tanggal_lapor || report.created_at)}</small></span>
    <span class="report-side">${statusBadge(report)}<b>›</b></span>
  </button>`;
}

function renderDashboard() {
  const reports = state.reports;
  const count = (status) =>
    reports.filter((report) => report.status === status).length;
  const processCount = reports.filter((report) =>
    ["diproses", "ditugaskan", "diangkut"].includes(report.status),
  ).length;
  $("#stat-total").textContent = reports.length;
  $("#stat-waiting").textContent = count("menunggu_verifikasi");
  $("#stat-process").textContent = processCount;
  $("#stat-done").textContent = count("selesai");
  const recent = reports.slice(0, 3);
  $("#recent-list").innerHTML = recent.length
    ? recent.map((report) => reportCard(report, true)).join("")
    : `<div class="empty-state"><span>✦</span><strong>Belum ada laporan</strong><p>Yuk mulai dari laporan pertamamu.</p><button class="text-button" data-page-link="reports" type="button">Buat laporan →</button></div>`;
  $("#last-sync").textContent = reports.length
    ? `Diperbarui ${new Intl.DateTimeFormat("id-ID", { hour: "2-digit", minute: "2-digit" }).format(new Date())}`
    : "Belum ada data";
}

function renderReports() {
  const inProcess = ["diproses", "ditugaskan", "diangkut"];
  const visible =
    state.filter === "all"
      ? state.reports
      : state.filter === "diproses"
        ? state.reports.filter((report) => inProcess.includes(report.status))
        : state.reports.filter((report) => report.status === state.filter);
  $("#reports-list").innerHTML = visible.length
    ? visible.map((report) => reportCard(report)).join("")
    : `<div class="empty-state"><span>◌</span><strong>Tidak ada laporan di filter ini</strong><p>Data akan muncul setelah kamu mengirim laporan.</p></div>`;
  const count = $("#history-count");
  if (count) {
    count.textContent = state.filter === "all"
      ? `${visible.length} laporan`
      : `${visible.length} dari ${state.reports.length} laporan`;
  }
  $$(".filter-button").forEach((button) =>
    button.classList.toggle(
      "is-active",
      button.dataset.filter === state.filter,
    ),
  );
}

function renderAll() {
  renderDashboard();
  renderReports();
}

async function refreshData(silent = false) {
  try {
    const [me, result] = await Promise.all([
      api("GET", "/me"),
      api("GET", "/laporan/mine"),
    ]);
    state.user = me.user;
    state.reports = result.reports || result.data || [];
    state.loadError = "";
    state.hasLoadedData = true;
    renderUser();
    renderAll();
    updateConnectionWarning();
    return true;
  } catch (error) {
    state.loadError = error.message;
    setConnectionStatus(error.status ? "connected" : "offline");
    updateConnectionWarning();
    if (error.status === 401 || error.status === 403) {
      localStorage.removeItem(TOKEN_KEY);
      stopPolling();
      state.user = null;
      showAuth(
        "login",
        error.status === 401
          ? "Sesi kamu sudah berakhir. Silakan login lagi."
          : "Akun ini tidak memiliki akses ke Portal Warga. Masuk dengan akun warga."
      );
      return false;
    }
    renderAll();
    if (!silent) toast(error.message, "error");
    return false;
  }
}

async function retrySessionRestore() {
  const button = $("#retry-session");
  button.disabled = true;
  setConnectionStatus("checking");
  const restored = await refreshData(true);
  if (restored) {
    showApp();
    startPolling();
  } else if (token()) {
    showAuth("login", "Server belum dapat dihubungi. Sesi tetap tersimpan; coba lagi.");
    button.hidden = false;
  }
  button.disabled = false;
}

function startPolling() {
  stopPolling();
  state.poll = setInterval(() => refreshData(true), 15000);
}

function stopPolling() {
  if (state.poll) clearInterval(state.poll);
  state.poll = null;
}

function openReportModal(id, showFullTimeline = false) {
  const report = state.reports.find((item) => Number(item.id) === Number(id));
  if (!report) return;
  const timeline = report.timeline || report.riwayat || [];
  const compactTimeline = compactReportTimeline(timeline);
  const visibleTimeline = showFullTimeline ? timeline : compactTimeline;
  const photo = safeUrl(report.photo || report.foto);
  const afterPhoto = safeUrl(report.completion_proof_photo || report.completionProofPhoto);
  const photoSteps = [
    photo
      ? `<a class="report-photo-step" href="${escapeHtml(photo)}" target="_blank" rel="noreferrer"><strong>Sebelum · foto warga</strong><img src="${escapeHtml(photo)}" alt="Kondisi sebelum ditangani, dilaporkan warga"></a>`
      : "",
    afterPhoto
      ? `<a class="report-photo-step" href="${escapeHtml(afterPhoto)}" target="_blank" rel="noreferrer"><strong>Sesudah · foto petugas</strong><img src="${escapeHtml(afterPhoto)}" alt="Kondisi sesudah ditangani, bukti petugas"></a>`
      : "",
  ].filter(Boolean);
  const photoComparison = photoSteps.length
    ? `<div class="report-photo-comparison ${photoSteps.length === 1 ? "is-single" : ""}">${photoSteps.join("")}</div>`
    : "";
  const timelineMarkup = visibleTimeline.length
    ? visibleTimeline.map((item) => `<div class="timeline-item"><i></i><div><strong>${escapeHtml(item.compactLabel || item.status_label || statusLabel(item.status))}</strong><small>${formatDate(item.tanggal_update, true)} · ${escapeHtml(item.changed_by || "Sistem")}</small>${item.note || item.catatan ? `<p>${escapeHtml(item.note || item.catatan)}</p>` : ""}</div></div>`).join("")
    : `<p class="muted-copy">Riwayat status belum tersedia.</p>`;
  const timelineToggle = timeline.length > compactTimeline.length
    ? `<button class="text-button timeline-toggle" type="button" data-toggle-report-history data-report-id="${Number(id)}" aria-expanded="${showFullTimeline}">${showFullTimeline ? "Ringkas progres" : `Lihat riwayat lengkap · ${timeline.length}`}</button>`
    : "";
  $("#modal-content").innerHTML =
    `<div class="modal-heading"><span class="eyebrow">DETAIL LAPORAN</span><h2>${escapeHtml(report.code || report.kode_laporan)}</h2><p>${escapeHtml(report.type || report.jenis)} · ${formatDate(report.report_date || report.tanggal_lapor)}</p></div>
    <div class="modal-status">${statusBadge(report)}<span>${escapeHtml(report.location || report.lokasi)}</span></div>
    ${photoComparison}
    <div class="detail-grid"><div><small>Lokasi</small><strong>${escapeHtml(report.location || report.lokasi)}</strong></div><div><small>Berat perkiraan</small><strong>${Number(report.weight_kg || report.berat_kg || 0).toLocaleString("id-ID")} kg</strong></div><div class="detail-full"><small>Deskripsi</small><strong>${escapeHtml(report.description || report.deskripsi || "Tidak ada deskripsi tambahan.")}</strong></div></div>
    <div class="timeline"><span class="eyebrow">PROGRES LAPORAN · ${showFullTimeline ? "RIWAYAT LENGKAP" : "TAHAP UTAMA"}</span>${timelineMarkup}${timelineToggle}</div>`;
  $("#report-modal").hidden = false;
  requestAnimationFrame(() => $("#report-modal").classList.add("is-open"));
}

function closeModal() {
  $("#report-modal").classList.remove("is-open");
  setTimeout(() => {
    $("#report-modal").hidden = true;
  }, 180);
}

async function handleLogin(event) {
  event.preventDefault();
  setMessage("login-message", "Memeriksa akun...", "info");
  const button = $("#login-form button[type=submit]");
  button.disabled = true;
  try {
    const result = await api("POST", "/auth/login", {
      username: $("#login-identity").value.trim(),
      password: $("#login-password").value,
      client: "web",
    });
    localStorage.setItem(TOKEN_KEY, result.token);
    state.user = result.user;
    $("#login-password").value = "";
    showApp();
    await refreshData();
    startPolling();
    toast("Selamat datang di portal Smart Waste.");
  } catch (error) {
    setMessage("login-message", error.message, "error");
  } finally {
    button.disabled = false;
  }
}

async function handleRegister(event) {
  event.preventDefault();
  setMessage("register-message", "Menyimpan akun...", "info");
  const button = $("#register-form button[type=submit]");
  button.disabled = true;
  try {
    await api("POST", "/auth/register", {
      name: $("#register-name").value.trim(),
      username: $("#register-username").value.trim(),
      email: $("#register-email").value.trim(),
      password: $("#register-password").value,
      client: "web",
    });
    $("#login-identity").value = $("#register-username").value.trim();
    $("#register-form").reset();
    showAuth("login", "Akun berhasil dibuat. Silakan masuk.", "success");
  } catch (error) {
    setMessage("register-message", error.message, "error");
  } finally {
    button.disabled = false;
  }
}

async function handleReport(event) {
  event.preventDefault();
  const messageId = "report-message";
  const photo = reportPhotoState.file;
  if (!photo) {
    setMessage(messageId, "Lampirkan foto kondisi awal sebelum mengirim laporan.", "error");
    $("#report-photo-section").scrollIntoView({ behavior: "smooth", block: "center" });
    $("#take-report-photo").focus({ preventScroll: true });
    return;
  }
  setMessage(messageId, "Mengirim laporan...", "info");
  const button = $("#report-form button[type=submit]");
  button.disabled = true;
  const form = new FormData();
  form.append("jenis_sampah", $("#report-type").value);
  form.append("berat_kg", $("#report-weight").value || "0");
  form.append("lokasi", $("#report-location").value.trim());
  form.append("deskripsi", $("#report-description").value.trim());
  form.append("tanggal_lapor", $("#report-date").value);
  form.append("foto", photo);
  try {
    await api("POST", "/laporan", form, true);
    $("#report-form").reset();
    $("#report-date").value = localDateInputValue();
    clearReportPhoto();
    await refreshData();
    goPage("dashboard");
    toast("Laporan dan foto kondisi awal terkirim untuk diverifikasi.");
  } catch (error) {
    setMessage(messageId, error.message, "error");
  } finally {
    button.disabled = false;
  }
}

async function handleProfile(event) {
  event.preventDefault();
  setMessage("profile-message", "Menyimpan perubahan...", "info");
  try {
    const result = await api("PUT", "/me", {
      name: $("#profile-name").value.trim(),
      email: $("#profile-email").value.trim(),
    });
    state.user = result.user;
    renderUser();
    setMessage("profile-message", "Profil berhasil diperbarui.", "success");
  } catch (error) {
    setMessage("profile-message", error.message, "error");
  }
}

async function handlePassword(event) {
  event.preventDefault();
  setMessage("password-message", "Memperbarui password...", "info");
  try {
    await api("PATCH", "/me/password", {
      old_password: $("#old-password").value,
      new_password: $("#new-password").value,
    });
    localStorage.removeItem(TOKEN_KEY);
    stopPolling();
    $("#password-form").reset();
    $("#login-password").value = "";
    showAuth(
      "login",
      "Password berhasil diganti. Silakan login kembali.",
      "success",
    );
  } catch (error) {
    setMessage("password-message", error.message, "error");
  }
}

async function logout() {
  try {
    await api("POST", "/auth/logout");
  } catch (_) {
    /* token tetap dibersihkan */
  }
  localStorage.removeItem(TOKEN_KEY);
  stopPolling();
  state.user = null;
  state.reports = [];
  state.loadError = "";
  state.hasLoadedData = false;
  $("#login-password").value = "";
  showAuth("login", "Kamu sudah keluar dari akun.", "success");
}

function bindEvents() {
  $("#show-login").addEventListener("click", () => showAuth("login"));
  $("#show-register").addEventListener("click", () => showAuth("register"));
  $("#login-form").addEventListener("submit", handleLogin);
  $("#retry-session").addEventListener("click", retrySessionRestore);
  $("#register-form").addEventListener("submit", handleRegister);
  $("#report-form").addEventListener("submit", handleReport);
  $("#take-report-photo").addEventListener("click", () => $("#report-photo-camera").click());
  $("#choose-report-photo").addEventListener("click", () => $("#report-photo-gallery").click());
  [$("#report-photo-camera"), $("#report-photo-gallery")].forEach((input) => {
    input.addEventListener("change", (event) => chooseReportPhoto(event.currentTarget.files[0], event.currentTarget));
  });
  $("#remove-report-photo").addEventListener("click", clearReportPhoto);
  $("#profile-form").addEventListener("submit", handleProfile);
  $("#password-form").addEventListener("submit", handlePassword);
  $("#logout-button").addEventListener("click", logout);
  $("#refresh-reports").addEventListener("click", () => refreshData());
  $("#retry-data").addEventListener("click", () => refreshData());
  $("#mobile-menu").addEventListener("click", () =>
    document.body.classList.toggle("mobile-nav-open"),
  );
  $("#user-menu").addEventListener("click", () => goPage("profile"));

  document.addEventListener("click", (event) => {
    const photoShortcut = event.target.closest("[data-photo-report]");
    if (photoShortcut) {
      event.preventDefault();
      goPage("reports", { scroll: false });
      requestAnimationFrame(() => {
        $("#report-photo-section").scrollIntoView({ behavior: "smooth", block: "center" });
        $("#take-report-photo").focus({ preventScroll: true });
      });
      return;
    }
    const historyToggle = event.target.closest("[data-toggle-report-history]");
    if (historyToggle) {
      openReportModal(
        historyToggle.dataset.reportId,
        historyToggle.getAttribute("aria-expanded") !== "true",
      );
      return;
    }
    const pageLink = event.target.closest("[data-page-link]");
    const quickCategory = event.target.closest("[data-report-type]");
    if (quickCategory) {
      $("#report-type").value = quickCategory.dataset.reportType;
    }
    if (pageLink) {
      event.preventDefault();
      goPage(pageLink.dataset.pageLink);
    }
    const reportItem = event.target.closest("[data-report-id]");
    if (reportItem) openReportModal(reportItem.dataset.reportId);
    if (event.target.closest("[data-close-modal]")) closeModal();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeModal();
  });

  $$(".filter-button").forEach((button) =>
    button.addEventListener("click", () => {
      state.filter = button.dataset.filter;
      renderReports();
    }),
  );
  $$(".password-toggle").forEach((button) =>
    button.addEventListener("click", () => {
      const input = document.getElementById(button.dataset.target);
      input.type = input.type === "password" ? "text" : "password";
      const isVisible = input.type === "text";
      button.classList.toggle("is-visible", isVisible);
      button.setAttribute(
        "aria-label",
        isVisible ? "Sembunyikan password" : "Tampilkan password",
      );
    }),
  );
  $("#use-location").addEventListener("click", () => {
    if (!navigator.geolocation) {
      toast("Browser ini tidak mendukung lokasi otomatis.", "error");
      return;
    }
    const button = $("#use-location");
    button.disabled = true;
    button.textContent = "⌖ Mencari lokasi...";
    navigator.geolocation.getCurrentPosition(
      (position) => {
        $("#report-location").value =
          `${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
        button.disabled = false;
        button.textContent = "⌖ Lokasi terisi";
      },
      () => {
        button.disabled = false;
        button.textContent = "⌖ Gunakan lokasi saya";
        toast("Lokasi belum bisa diakses. Isi lokasi secara manual.", "error");
      },
      { enableHighAccuracy: true, timeout: 8000 },
    );
  });
}

async function boot() {
  $("#footer-year").textContent = `© ${new Date().getFullYear()}`;
  $("#report-date").value = localDateInputValue();
  bindEvents();
  if (!token()) {
    showAuth("login");
    return;
  }
  if (await refreshData(true)) {
    showApp();
    startPolling();
  } else if (token()) {
    showAuth("login", "Server belum dapat dihubungi. Sesi tetap tersimpan; coba lagi.");
    $("#retry-session").hidden = false;
  }
}

document.addEventListener("DOMContentLoaded", boot);
