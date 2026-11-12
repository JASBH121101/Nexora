(() => {
  const demos = window.NEXORA_DEMOS || [];
  const $ = (selector) => document.querySelector(selector);
  const make = (tag, text) => {
    const element = document.createElement(tag);
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
  };
  const safeImageSource = (value) => {
    try {
      const url = new URL(String(value), window.location.href);
      if (url.protocol === "https:" || url.protocol === "http:") return url.href;
    } catch {
      // Invalid image URLs use the local fallback below.
    }
    return "assets/img/product-placeholder.svg";
  };
  const businessSelect = $("#business-select");
  const categoryList = $("#category-list");
  const productGrid = $("#product-grid");
  const searchInput = $("#product-search");
  const emptyState = $("#empty-state");
  const toast = $("#toast");
  const dialog = $("#product-dialog");
  let business = demos.find((item) => item.id === businessSelect?.value) || demos[0];
  let settings = business ? NexoraStore.getSettings(business) : {};
  let products = business ? NexoraStore.getProducts(business) : [];
  let activeCategory = "all";
  let searchTerm = "";
  let toastTimer;

  if (!business) return;

  const showToast = (message) => {
    toast.textContent = message;
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 2600);
  };
  const buildWhatsApp = (message) => NexoraStore.whatsappURL(settings.whatsapp, message);
  const setWhatsAppLink = (element, message) => {
    const url = buildWhatsApp(message);
    element.href = url || "#";
    element.setAttribute("aria-disabled", String(!url));
    element.onclick = url ? null : (event) => {
      event.preventDefault();
      showToast("Configura un número de WhatsApp válido en el panel de demostración.");
    };
  };

  const renderCategories = () => {
    const labels = ["Todos", ...business.categories];
    const buttons = labels.map((label, index) => {
      const value = index === 0 ? "all" : label;
      const button = make("button", label);
      button.classList.add("category-chip");
      if (activeCategory === value) button.classList.add("is-active");
      button.type = "button";
      button.dataset.category = value;
      return button;
    });
    categoryList.replaceChildren(...buttons);
  };

  const productCard = (product) => {
    const article = make("article");
    article.classList.add("product-card");
    const imageWrap = make("div");
    imageWrap.classList.add("product-image");
    const image = make("img");
    image.src = safeImageSource(product.image);
    image.alt = product.name || "Producto del catálogo";
    image.loading = "lazy";
    image.addEventListener("error", () => { image.src = "assets/img/product-placeholder.svg"; }, { once: true });
    imageWrap.append(image);

    if (product.tag) {
      const tag = make("span", product.tag);
      tag.classList.add("product-tag");
      imageWrap.append(tag);
    }
    const quickButton = make("button", "↗");
    quickButton.classList.add("product-quick");
    quickButton.type = "button";
    quickButton.dataset.productId = product.id;
    quickButton.setAttribute("aria-label", "Ver detalles de " + product.name);
    imageWrap.append(quickButton);

    const info = make("div");
    info.classList.add("product-info");
    const category = make("span", product.category);
    category.classList.add("product-category");
    info.append(category);
    info.append(make("h3", product.name));
    const detail = make("p", product.detail || "Consulta más información");
    detail.classList.add("product-detail");
    info.append(detail);
    const priceRow = make("div");
    priceRow.classList.add("price-row");
    const price = make("span", NexoraStore.money(product.price));
    price.classList.add("product-price");
    priceRow.append(price);
    if (Number(product.oldPrice) > Number(product.price)) {
      const oldPrice = make("del", NexoraStore.money(product.oldPrice));
      oldPrice.classList.add("old-price");
      priceRow.append(oldPrice);
    }
    info.append(priceRow);
    article.append(imageWrap, info);
    return article;
  };

  const filteredProducts = () => products.filter((product) => {
    const categoryMatch = activeCategory === "all" || product.category === activeCategory;
    const text = [product.name, product.category, product.description, product.detail].join(" ").toLowerCase();
    return categoryMatch && text.includes(searchTerm);
  });

  const renderProducts = () => {
    const matches = filteredProducts();
    productGrid.replaceChildren(...matches.map(productCard));
    emptyState.hidden = matches.length > 0;
    $("#results-count").textContent = matches.length + (matches.length === 1 ? " producto" : " productos");
  };

  const renderBusiness = () => {
    settings = NexoraStore.getSettings(business);
    products = NexoraStore.getProducts(business);
    activeCategory = "all";
    searchTerm = "";
    searchInput.value = "";
    $("#business-kicker").textContent = settings.name.toUpperCase() + " · " + business.tagline;
    const headline = $("#hero-title");
    const secondLine = make("span", business.headline[1]);
    headline.replaceChildren(document.createTextNode(business.headline[0]), document.createElement("br"), secondLine);
    $("#hero-description").textContent = business.description;
    $("#hero-image").src = safeImageSource(business.heroImage);
    $("#hero-image").alt = "Imagen ilustrativa de " + settings.name;
    $("#hero-feature-label").textContent = business.featureLabel;
    $("#hero-total").textContent = String(products.length).padStart(2, "0");
    $("#business-contact-copy").textContent = settings.contact;
    setWhatsAppLink($("#header-whatsapp"), "Hola, me gustaría recibir más información de " + settings.name + ".");
    setWhatsAppLink($("#main-whatsapp"), "Hola, me gustaría recibir más información del catálogo de " + settings.name + ".");
    renderCategories();
    renderProducts();
  };

  const openProduct = (id) => {
    const product = products.find((item) => item.id === id);
    if (!product) return;
    const image = $("#dialog-image");
    image.src = safeImageSource(product.image);
    image.alt = product.name;
    image.onerror = () => { image.src = "assets/img/product-placeholder.svg"; };
    $("#dialog-category").textContent = product.category;
    $("#dialog-title").textContent = product.name;
    $("#dialog-description").textContent = product.description || "Consulta con el negocio para conocer más detalles.";
    const attributeList = $("#dialog-attributes");
    attributeList.replaceChildren();
    if (product.detail) {
      const attribute = make("span", product.detail);
      attribute.classList.add("attribute-pill");
      attributeList.append(attribute);
    }

    const price = $("#dialog-price");
    price.replaceChildren(document.createTextNode(NexoraStore.money(product.price)));
    if (Number(product.oldPrice) > Number(product.price)) {
      const oldPrice = make("del", NexoraStore.money(product.oldPrice));
      price.append(oldPrice);
    }
    setWhatsAppLink($("#dialog-whatsapp"), "Hola, me interesa " + product.name + " (" + NexoraStore.money(product.price) + "). ¿Me pueden brindar más información?");
    dialog.showModal();
  };

  businessSelect.addEventListener("change", () => {
    business = demos.find((item) => item.id === businessSelect.value) || demos[0];
    renderBusiness();
  });
  categoryList.addEventListener("click", (event) => {
    const button = event.target.closest("[data-category]");
    if (!button) return;
    activeCategory = button.dataset.category;
    renderCategories();
    renderProducts();
  });
  productGrid.addEventListener("click", (event) => {
    const button = event.target.closest("[data-product-id]");
    if (button) openProduct(button.dataset.productId);
  });
  searchInput.addEventListener("input", () => {
    searchTerm = searchInput.value.trim().toLocaleLowerCase("es");
    renderProducts();
  });
  $("#clear-filters").addEventListener("click", () => {
    activeCategory = "all";
    searchTerm = "";
    searchInput.value = "";
    renderCategories();
    renderProducts();
  });
  $("#dialog-close").addEventListener("click", () => dialog.close());
  dialog.addEventListener("click", (event) => {
    if (event.target === dialog) dialog.close();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "/" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
      event.preventDefault();
      searchInput.focus();
    }
  });
  $("#current-year").textContent = String(new Date().getFullYear());
  $("#hero-image").addEventListener("error", () => {
    $("#hero-image").src = "assets/img/product-placeholder.svg";
  }, { once: true });
  renderBusiness();
})();