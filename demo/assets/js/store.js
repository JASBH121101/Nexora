(() => {
  const PREFIX = "nexora-demo-v1";
  const safeRead = (key, fallback) => {
    try {
      const raw = localStorage.getItem(PREFIX + ":" + key);
      return raw === null ? structuredClone(fallback) : JSON.parse(raw);
    } catch {
      return structuredClone(fallback);
    }
  };
  const safeWrite = (key, value) => {
    try {
      localStorage.setItem(PREFIX + ":" + key, JSON.stringify(value));
      return true;
    } catch {
      return false;
    }
  };
  const escapeHTML = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
  })[char]);
  const money = (amount, currency = "USD") => {
    const code = /^[A-Z]{3}$/.test(String(currency || "")) ? currency : "USD";
    try {
      return new Intl.NumberFormat("es-NI", {
        style: "currency", currency: code, maximumFractionDigits: 2
      }).format(Number(amount) || 0);
    } catch {
      return code + " " + (Number(amount) || 0).toFixed(2);
    }
  };
  const digitsOnly = (value) => String(value ?? "").replace(/\D/g, "");
  const whatsappURL = (number, message) => {
    const digits = digitsOnly(number);
    if (digits.length < 8 || digits.length > 15) return "";
    return "https://wa.me/" + digits + "?text=" + encodeURIComponent(message);
  };
  const getProducts = (business) => {
    const businessId = String(business?.id ?? "");
    const fallback = Array.isArray(business?.products) ? business.products : [];
    const stored = safeRead("products:" + businessId, fallback);
    return Array.isArray(stored) ? stored : structuredClone(fallback);
  };
  const saveProducts = (businessId, products) => (
    Array.isArray(products) && safeWrite("products:" + businessId, products)
  );
  const getSettings = (business) => {
    const stored = safeRead("settings:" + String(business?.id ?? ""), {});
    const settings = stored && typeof stored === "object" && !Array.isArray(stored)
      ? stored
      : {};
    return {
      name: business?.name ?? "",
      whatsapp: business?.whatsapp ?? "",
      contact: business?.contact ?? "",
      ...settings
    };
  };
  const saveSettings = (businessId, settings) => (
    settings !== null
    && typeof settings === "object"
    && !Array.isArray(settings)
    && safeWrite("settings:" + businessId, settings)
  );
  window.NexoraStore = { escapeHTML, money, whatsappURL, getProducts, saveProducts, getSettings, saveSettings };
})();