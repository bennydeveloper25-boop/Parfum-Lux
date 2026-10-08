/* Parfum Lux — Frontend App */
/* API base configurable */
const API_BASE = 'http://localhost:8000/api';

// --- Helpers ---
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

function toast(message, type = 'info') {
  const container = $('#toastContainer');
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  container.appendChild(el);
  setTimeout(() => { el.style.animation = 'slideIn .3s reverse'; setTimeout(() => el.remove(), 300); }, 3500);
}

function formatPrice(n) {
  try {
    return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(n);
  } catch {
    return 'C$ ' + Number(n).toLocaleString('es-NI');
  }
}

async function api(path, options = {}) {
  const { headers: optHeaders, ...rest } = options;
  const headers = { 'Content-Type': 'application/json', ...(optHeaders || {}) };
  let res;
  try {
    res = await fetch(`${API_BASE}${path}`, { headers, ...rest });
  } catch { throw new Error('Sin conexión con la API (¿servidor :8000 apagado?).'); }
  let data = {};
  try { data = await res.json(); }
  catch { throw new Error(`Respuesta no válida del servidor (HTTP ${res.status}). Revisa la consola del backend.`); }
  if (!res.ok) throw new Error(data.error?.message || `HTTP ${res.status}`);
  if (!('data' in data)) throw new Error('Respuesta inesperada del servidor (sin campo data).');
  return data;
}

// --- State ---
let products = [];
let families = [];
let cart = JSON.parse(localStorage.getItem('pl_cart') || '[]');
let selectedVariant = null;

// --- DOM Refs ---
const featuredGrid = $('#featuredGrid');
const productsGrid = $('#productsGrid');
const familyFilter = $('#familyFilter');
const sortFilter = $('#sortFilter');
const bagBtn = $('#bagBtn');
const badge = $('#badge');
const productModal = $('#productModal');
const modalBody = $('#modalBody');
const modalClose = $('#modalClose');
const contactForm = $('#contactForm');
const formNote = $('#formNote');
const searchBtn = $('#searchBtn');
const userBtn = $('#userBtn');

// --- Init ---
async function init() {
  updateBadge();
  refreshHeaderAuth();
  await loadFamilies();
  await loadProducts();
  renderFeatured();
  renderProducts();
  bindEvents();
}

async function loadFamilies() {
  try {
    const data = await api('/families');
    families = data.data || [];
    familyFilter.innerHTML = '<option value="">Todas las familias</option>' +
      families.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
  } catch (e) { console.warn('Families load failed', e); }
}

async function loadProducts() {
  try {
    productsGrid.classList.add('loading');
    const data = await api('/products?limit=100');
    products = data.data || [];
    productsGrid.classList.remove('loading');
  } catch (e) {
    productsGrid.classList.remove('loading');
    toast('No se pudo cargar la colección', 'error');
    console.error(e);
  }
}

function renderFeatured() {
  const featured = products.filter(p => p.is_featured).slice(0, 3);
  featuredGrid.innerHTML = featured.map(cardHTML).join('');
  bindCardEvents(featuredGrid);
}

function renderProducts() {
  let filtered = [...products];
  const fam = familyFilter.value;
  if (fam) filtered = filtered.filter(p => p.family_id == fam);
  const sort = sortFilter.value;
  if (sort === 'price-asc') filtered.sort((a,b) => a.price_base - b.price_base);
  else if (sort === 'price-desc') filtered.sort((a,b) => b.price_base - a.price_base);
  else if (sort === 'name') filtered.sort((a,b) => a.name.localeCompare(b.name));
  else filtered.sort((a,b) => (b.is_featured - a.is_featured) || a.id - b.id);

  productsGrid.innerHTML = filtered.map(cardHTML).join('');
  bindCardEvents(productsGrid);
}

function cardHTML(p) {
  const img = p.image_url
    ? `<img src="${p.image_url}" alt="${p.name} — Parfum Lux" loading="lazy" onerror="this.closest('.card-visual').classList.add('img-fallback');this.remove()">`
    : `<span class="card-initials" aria-hidden="true">${p.name.split(' ').map(w=>w[0]).slice(0,2).join('')}</span>`;
  return `
    <article class="product-card" role="listitem" data-id="${p.id}">
      <div class="card-visual">${img}${p.is_featured ? '<span class="card-flag">Destacado</span>' : ''}</div>
      <div class="card-body">
        ${p.family_name ? `<span class="card-family">${p.family_name}</span>` : ''}
        <h3 class="card-name">${p.name}</h3>
        ${p.tagline ? `<p class="card-tagline">"${p.tagline}"</p>` : ''}
        ${p.concentration ? `<p class="card-conc">${p.concentration}</p>` : ''}
        <div class="card-footer">
          <span class="card-price">${formatPrice(p.price_base)}</span>
          <button class="btn btn-primary btn-sm" data-action="view">Ver</button>
        </div>
      </div>
    </article>
  `;
}

function bindCardEvents(container) {
  $$('.product-card', container).forEach(card => {
    card.querySelector('[data-action="view"]').addEventListener('click', () => openModal(card.dataset.id));
    card.querySelector('.card-visual').addEventListener('click', () => openModal(card.dataset.id));
  });
}

async function openModal(id) {
  const p = products.find(x => x.id == id);
  if (!p) return;
  try {
    const detail = await api(`/products/${id}`);
    renderModal(detail.data);
    productModal.hidden = false;
    document.body.style.overflow = 'hidden';
  } catch (e) {
    toast('Error cargando detalle', 'error');
  }
}

function renderModal(p) {
  const notesByType = { top: [], heart: [], base: [] };
  (p.notes || []).forEach(n => notesByType[n.type]?.push(n.name));
  const notesHTML = Object.entries(notesByType).map(([type, arr]) =>
    arr.length ? `<div><span class="cap" style="color:var(--lavande)">${type.charAt(0).toUpperCase()+type.slice(1)}:</span> ${arr.map(n => `<span class="note-tag">${n}</span>`).join('')}</div>`
  : '').join('');

  const variants = p.variants || [];
  const firstVar = variants[0];
  selectedVariant = firstVar?.id || null;

  modalBody.innerHTML = `
    <div class="modal-product">
      <div class="modal-visual">${p.image_url ? `<img src="${p.image_url}" alt="${p.name} — Parfum Lux" onerror="this.remove()">` : `<span class="card-initials">${p.name.slice(0,2)}</span>`}</div>
      <div class="modal-info">
        ${p.family_name ? `<span class="modal-family">${p.family_name}</span>` : ''}
        <h3>${p.name}</h3>
        ${p.tagline ? `<p class="modal-tagline" style="font-style:italic;color:var(--gris);margin-bottom:1rem;">"${p.tagline}"</p>` : ''}
        <p class="modal-desc">${p.description || ''}</p>
        ${notesHTML ? `<div class="modal-notes">${notesHTML}</div>` : ''}
        ${variants.length ? `
          <div class="modal-variants" role="radiogroup" aria-label="Tamaño">
            ${variants.map(v => `
              <button type="button" class="variant-btn ${v.id === selectedVariant ? 'selected' : ''}" data-vid="${v.id}" ${v.id === selectedVariant ? 'aria-pressed="true"' : 'aria-pressed="false"'}>${v.size_ml} ml — ${formatPrice(v.price)}</button>
            `).join('')}
          </div>
        ` : ''}
        <div class="modal-price" id="modalPrice">${firstVar ? formatPrice(firstVar.price) : formatPrice(p.price_base)}</div>
        <div class="modal-actions">
          <button class="btn btn-primary" id="addToCartBtn" style="flex:1">Añadir a la bolsa</button>
          <button class="btn btn-ghost" id="closeModalBtn">Seguir viendo</button>
        </div>
        <div class="reviews" id="revBox"><h4>Opiniones</h4><div id="revList"><p class="muted small">Cargando…</p></div>
          <form id="revForm" class="form-grid">
            <label>Tu nombre<input id="r_name" required maxlength="120"></label>
            <label>Puntuación<select id="r_rating"><option value="5">5 — Excepcional</option><option value="4">4 — Muy bueno</option><option value="3">3 — Correcto</option><option value="2">2 — Flojo</option><option value="1">1 — Malo</option></select></label>
            <label>Título<input id="r_title" maxlength="150" placeholder="En una frase"></label>
            <label>Comentario<textarea id="r_comment" rows="3" placeholder="¿Qué te pareció?"></textarea></label>
            <button class="btn btn-ghost" type="submit">Publicar opinión</button>
            <p class="form-note" id="r_msg"></p>
          </form>
        </div>
      </div>
    </div>
  `;

  // Bind modal events
  $$('.variant-btn', modalBody).forEach(btn => {
    btn.addEventListener('click', () => {
      $$('.variant-btn', modalBody).forEach(b => { b.classList.remove('selected'); b.setAttribute('aria-pressed', 'false'); });
      btn.classList.add('selected');
      btn.setAttribute('aria-pressed', 'true');
      selectedVariant = parseInt(btn.dataset.vid);
      const v = variants.find(x => x.id === selectedVariant);
      $('#modalPrice').textContent = v ? formatPrice(v.price) : formatPrice(p.price_base);
    });
  });
  $('#addToCartBtn').addEventListener('click', () => addToCart(p, selectedVariant));
  $('#closeModalBtn').addEventListener('click', closeModal);
  const loadRev = async () => {
    try {
      const d = await api(`/products/${p.id}/reviews`);
      $('#revList').innerHTML = d.data.length ? d.data.map(r => `<p><b>${r.author_name}</b> · ${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}<br><i>${r.title || ''}</i><br>${r.comment || ''}</p>`).join('') : '<p class="muted small">Sé el primero en opinar.</p>';
    } catch { $('#revList').innerHTML = '<p class="muted small">No se pudieron cargar.</p>'; }
  };
  loadRev();
  $('#revForm').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await api('/reviews', { method: 'POST', body: JSON.stringify({ product_id: p.id, author_name: $('#r_name').value, rating: Number($('#r_rating').value), title: $('#r_title').value, comment: $('#r_comment').value }) });
      $('#r_msg').textContent = 'Gracias, opinión publicada.';
      $('#revForm').reset(); loadRev(); toast('Opinión publicada', 'success');
    } catch (err) { $('#r_msg').textContent = err.message; }
  });
}

function closeModal() {
  productModal.hidden = true;
  document.body.style.overflow = '';
  selectedVariant = null;
}

function addToCart(product, variantId) {
  const variant = (product.variants || []).find(v => v.id === variantId) || { id: 0, size_ml: 50, price: product.price_base, sku: product.sku };
  const existing = cart.find(i => i.variant_id === variant.id);
  if (existing) existing.qty += 1;
  else cart.push({ variant_id: variant.id, product_name: product.name, size_ml: variant.size_ml, qty: 1, unit_price: variant.price, sku: variant.sku });
  saveCart();
  updateBadge();
  toast(`${product.name} ${variant.size_ml}ml añadido a la bolsa`, 'success');
  closeModal();
}

function saveCart() { localStorage.setItem('pl_cart', JSON.stringify(cart)); }
function updateBadge() { const total = cart.reduce((s,i)=>s+i.qty,0); badge.textContent = total; badge.classList.toggle('on', total>0); if (total>0) { badge.classList.remove('pop'); void badge.offsetWidth; badge.classList.add('pop'); } }

function bindEvents() {
  bindAuthOnce();
  familyFilter.addEventListener('change', renderProducts);
  sortFilter.addEventListener('change', renderProducts);
  modalClose.addEventListener('click', closeModal);
  productModal.querySelector('.modal-backdrop').addEventListener('click', closeModal);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !productModal.hidden) closeModal(); });

  contactForm.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(contactForm);
    const data = Object.fromEntries(fd);
    try {
      await api('/contact', { method: 'POST', body: JSON.stringify(data) });
      formNote.textContent = '¡Gracias! Te responderemos en menos de 24h.';
      formNote.style.color = 'var(--lavande)';
      contactForm.reset();
    } catch (err) {
      formNote.textContent = 'Error al enviar. Inténtalo de nuevo.';
      formNote.style.color = '#B84A4A';
    }
  });

  // Smooth scroll for anchor links (solo #reales; los #! son modales)
  $$('a[href^="#"]').forEach(a => {
    const href = a.getAttribute('href');
    if (href === '#!') return;
    a.addEventListener('click', e => {
      const id = href.slice(1);
      const target = document.getElementById(id);
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); history.pushState(null, '', '#' + id); }
    });
  });

  // Footer informativo: todo funcional
  $$('[data-info]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); openInfo(a.dataset.info); }));
  $$('#infoModal [data-close]').forEach(b => b.addEventListener('click', () => { $('#infoModal').hidden = true; }));

  // Header: buscador integrado + cuenta + bolsa
  const overlay = $('#searchOverlay'), sInput = $('#searchInput'), sRes = $('#searchResults');
  const openSearch = () => { overlay.hidden = false; searchBtn.setAttribute('aria-expanded', 'true'); sInput.value = ''; paintSearch(''); setTimeout(() => sInput.focus(), 30); };
  const closeSearch = () => { overlay.hidden = true; searchBtn.setAttribute('aria-expanded', 'false'); };
  searchBtn.addEventListener('click', openSearch);
  $('#searchClose').addEventListener('click', closeSearch);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeSearch(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !overlay.hidden) closeSearch(); });
  let searchT = 0;
  sInput.addEventListener('input', () => { clearTimeout(searchT); searchT = setTimeout(() => paintSearch(sInput.value), 140); });
  function paintSearch(q) {
    const needle = q.trim().toLowerCase();
    const hits = !needle ? products.slice(0, 4) : products.filter(p => (p.name + ' ' + (p.tagline || '') + ' ' + (p.family_name || '')).toLowerCase().includes(needle)).slice(0, 8);
    sRes.innerHTML = (!needle ? '<p class="muted small">Sugerencias de la Maison</p>' : '') + (hits.length ? hits.map(p => `
      <button class="s-hit" data-id="${p.id}" role="option">
        ${p.image_url ? `<img src="${p.image_url}" alt="" loading="lazy">` : `<span class="s-init">${p.name.slice(0, 2)}</span>`}
        <span><b>${p.name}</b><small>${p.family_name || ''} · ${formatPrice(p.price_base)}</small></span>
      </button>`).join('') : '<p class="muted">Sin resultados. Prueba “rosa”, “nuit”, “ámbar”…</p>');
    $$('.s-hit', sRes).forEach(b => b.onclick = () => { closeSearch(); openModal(b.dataset.id); });
    if (needle.length > 1) api('/searches', { method: 'POST', body: JSON.stringify({ q: needle }) }).catch(() => {});
  }
  userBtn.addEventListener('click', () => openAuth());
  bagBtn.addEventListener('click', () => openInfo('bag'));

  const footNews = $('#footNews');
  if (footNews) footNews.addEventListener('submit', async e => {
    e.preventDefault();
    const em = $('#footNewsEmail').value.trim();
    try {
      await api('/newsletter', { method: 'POST', body: JSON.stringify({ email: em }) });
      $('#footNewsMsg').textContent = 'Unido. Revisa tu correo.';
      footNews.reset(); toast('Suscripción lista', 'success');
    } catch (err) { $('#footNewsMsg').textContent = err.message; }
  });
}

const INFO = {
  shipping: ['Envíos y devoluciones', '<p>Masaya y Managua 24/72h, resto del país 3-5 días. Envío gratis desde C$3,000 (normal C$150). Cada pedido incluye 2 muestras.</p><p>Devoluciones 30 días en frascos sin abrir. Escríbenos desde <a href="#contact" data-goto="contact">Contacto</a>.</p>'],
  legal: ['Aviso legal', '<p>Parfum Lux · Masaya, Nicaragua. Proyecto demostrativo creado por <b>BennyDeveloper</b>. Precios en córdobas (NIO).</p>'],
  privacy: ['Privacidad', '<p>Solo usamos tu email para tu pedido y mensajes. No vendemos datos. Puedes pedir borrarlos en Contacto.</p>'],
  terms: ['Condiciones', '<p>Compra mayor de 18 años. Pago contra entrega en Masaya, transferencia BAC/Lafise o tarjeta. Stock sujeto a disponibilidad.</p>'],
  cookies: ['Cookies', '<p>Solo técnicas (carrito en tu navegador y sesión). Sin rastreadores de terceros.</p>'],
};
async function openInfo(key) {
  const box = $('#infoBody'), modal = $('#infoModal');
  if (key === 'faq') {
    try {
      const d = await api('/faqs');
      box.innerHTML = '<h3>Preguntas frecuentes</h3>' + d.data.map(f => `<p><b>${f.question}</b><br>${f.answer}</p>`).join('');
    } catch { box.innerHTML = '<h3>Preguntas frecuentes</h3><p>No disponible ahora mismo.</p>'; }
  } else if (key === 'account') {
    openAuth();
    return;
  } else if (key === 'newsletter') {
    box.innerHTML = `<h3>Newsletter Lux</h3><p class="muted">Una carta al mes: lanzamientos y ritos olfativos. Sin spam.</p>
      <div class="form-grid"><label>Email<input id="n_email" type="email" placeholder="tu@email.com"></label>
      <button class="btn btn-primary" id="n_go">Suscribirme</button><p class="form-note" id="n_msg"></p></div>`;
    $('#n_go').onclick = async () => {
      try { await api('/newsletter', { method: 'POST', body: JSON.stringify({ email: $('#n_email').value }) }); $('#n_msg').textContent = 'Suscripción confirmada. Bienvenido a la Maison.'; toast('Suscrito', 'success'); }
      catch (e) { $('#n_msg').textContent = e.message; }
    };
  } else if (key === 'bag') {
    if (!cart.length) { box.innerHTML = '<h3>Tu bolsa</h3><p>Está vacía. Descubre la colección.</p>'; }
    else {
      const total = cart.reduce((s, i) => s + i.unit_price * i.qty, 0);
      box.innerHTML = '<h3>Tu bolsa</h3><ul>' + cart.map(i => `<li>${i.qty} × ${i.product_name} ${i.size_ml}ml — ${formatPrice(i.unit_price * i.qty)}</li>`).join('') + `</ul><p><b>Total: ${formatPrice(total)}</b> ${total >= 3000 ? '(envío gratis)' : '+ envío C$150'}</p><button class="btn btn-primary" id="checkoutBtn">Finalizar pedido</button> <button class="btn btn-ghost" id="clearBtn">Vaciar</button><p class="muted small" id="co_msg"></p>`;
      $('#clearBtn').onclick = () => { cart = []; saveCart(); updateBadge(); openInfo('bag'); };
      $('#checkoutBtn').onclick = async () => {
        try { const d = await api('/orders', { method: 'POST', headers: localStorage.getItem('pl_token') ? { Authorization: 'Bearer ' + localStorage.getItem('pl_token') } : {}, body: JSON.stringify({ items: cart }) }); cart = []; saveCart(); updateBadge(); $('#co_msg').textContent = 'Pedido #' + d.data.id + ' creado. ¡Gracias!'; toast('Pedido creado', 'success'); }
        catch (e) { $('#co_msg').textContent = e.message; }
      };
    }
  } else {
    const [t, h] = INFO[key] || ['Info', ''];
    box.innerHTML = `<h3>${t}</h3>${h}`;
    const g = box.querySelector('[data-goto]');
    if (g) g.onclick = e => { e.preventDefault(); modal.hidden = true; document.getElementById('contact').scrollIntoView({ behavior: 'smooth' }); };
  }
  modal.hidden = false;
}

// Auth integrado: si hay sesión muestra PERFIL (pedidos, editar nombre, salir); si no, pestañas Entrar/Crear
let authMode = 'login';
function openAuth() {
  const m = $('#authModal');
  if (localStorage.getItem('pl_token')) renderProfile();
  else {
    $('#authTabs').style.display = '';
    $('#authForm').style.display = '';
    $('#profileBox').style.display = 'none';
    paintAuthState();
  }
  m.hidden = false;
}
function paintAuthState() {
  const em = localStorage.getItem('pl_email') || '';
  if (em) $('#authEmail').value = em;
  $('#authMsg').textContent = '';
  refreshHeaderAuth();
}
function renderProfile() {
  $('#authTabs').style.display = 'none';
  $('#authForm').style.display = 'none';
  const box = $('#profileBox');
  box.style.display = '';
  const name = localStorage.getItem('pl_name') || 'Miembro Lux';
  const email = localStorage.getItem('pl_email') || '';
  const isAdmin = email === 'admin@parfumlux.com';
  box.innerHTML = `
    <div class="profile-head"><span class="avatar">${name.trim().charAt(0).toUpperCase()}</span>
      <div><b>${name}</b><small>${email}</small></div></div>
    ${isAdmin ? '<a class="btn btn-primary" href="admin/index.html">Abrir panel administrativo</a>' : ''}
    <div class="profile-row"><button class="btn btn-ghost" id="myOrdersBtn">Mis pedidos</button>
    <button class="btn btn-ghost" id="editNameBtn">Editar mi nombre</button></div>
    <div id="myOrders"></div>
    <div id="editNameBox" style="display:none" class="form-grid">
      <label>Nuevo nombre<input id="newName" value="${name}" maxlength="120"></label>
      <button class="btn btn-primary" id="saveNameBtn">Guardar</button>
    </div>
    <button class="btn danger" id="logoutBtn2">Salir de esta cuenta</button>`;
  $('#logoutBtn2').onclick = () => { ['pl_token', 'pl_email', 'pl_name'].forEach(k => localStorage.removeItem(k)); refreshHeaderAuth(); $('#authModal').hidden = true; toast('Sesión cerrada'); };
  $('#editNameBtn').onclick = () => { const b = $('#editNameBox'); b.style.display = b.style.display === 'none' ? '' : 'none'; };
  $('#saveNameBtn').onclick = async () => {
    try {
      const d = await api('/auth/profile', { method: 'PUT', headers: authHeader(), body: JSON.stringify({ name: $('#newName').value.trim() }) });
      localStorage.setItem('pl_name', d.data.name);
      toast('Nombre actualizado', 'success'); renderProfile(); refreshHeaderAuth();
    } catch (e) { toast(e.message, 'error'); }
  };
  $('#myOrdersBtn').onclick = async () => {
    const box2 = $('#myOrders');
    box2.innerHTML = '<p class="muted small">Cargando…</p>';
    try {
      const d = await api('/orders', { headers: authHeader() });
      box2.innerHTML = d.data.length ? d.data.map(o => `<p><b>#${o.id}</b> · ${(o.created_at || '').slice(0, 10)} · ${formatPrice(o.total)} · <span class="badge ${o.status}">${o.status}</span></p>`).join('') : '<p class="muted small">Aún no tienes pedidos.</p>';
    } catch (e) { box2.innerHTML = `<p class="muted small">${e.message}</p>`; }
  };
}
function authHeader() {
  const t = localStorage.getItem('pl_token');
  return t ? { Authorization: 'Bearer ' + t } : {};
}
function refreshHeaderAuth() {
  const t = localStorage.getItem('pl_token'), e = localStorage.getItem('pl_email');
  const btn = $('#userBtn');
  if (btn) {
    btn.classList.toggle('logged', !!(t && e));
    btn.setAttribute('aria-label', t && e ? ('Mi perfil: ' + e) : 'Mi cuenta');
    btn.setAttribute('title', t && e ? ('Mi perfil: ' + e) : 'Mi cuenta');
  }
  const al = $('#adminLink');
  if (al) al.hidden = !(t && e === 'admin@parfumlux.com');
}
function bindAuthOnce() {
  const m = $('#authModal');
  m.querySelectorAll('[data-close]').forEach(b => b.onclick = () => { m.hidden = true; });
  const setMode = md => {
    authMode = md;
    $('#tabLogin').classList.toggle('active', md === 'login');
    $('#tabRegister').classList.toggle('active', md === 'register');
    $('#nameRow').style.display = md === 'register' ? '' : 'none';
    $('#authSubmit').textContent = md === 'login' ? 'Entrar' : 'Crear mi cuenta';
  };
  $('#tabLogin').onclick = () => setMode('login');
  $('#tabRegister').onclick = () => setMode('register');
  $('#passToggle').onclick = () => { const i = $('#authPass'); i.type = i.type === 'password' ? 'text' : 'password'; $('#passToggle').textContent = i.type === 'password' ? 'Ver' : 'Ocultar'; };
  $('#authSubmit').onclick = async () => {
    const email = $('#authEmail').value.trim(), pass = $('#authPass').value, btn = $('#authSubmit');
    $('#authMsg').textContent = '';
    if (!email || pass.length < 6) { $('#authMsg').textContent = 'Email válido y mínimo 6 caracteres.'; return; }
    btn.disabled = true; btn.textContent = 'Un momento…';
    try {
      const path = authMode === 'login' ? '/auth/login' : '/auth/register';
      const body = authMode === 'login' ? { email, password: pass } : { name: $('#authName').value.trim() || email.split('@')[0], email, password: pass };
      if (authMode === 'register' && !$('#authName').value.trim()) { /* nombre opcional, se deriva del email */ }
      const d = await api(path, { method: 'POST', body: JSON.stringify(body) });
      const tk = d?.data?.token, em = d?.data?.user?.email, nm = d?.data?.user?.name;
      if (!tk) throw new Error('El servidor no devolvió token. Revisa credenciales o la consola del backend.');
      localStorage.setItem('pl_token', tk);
      localStorage.setItem('pl_email', em || email);
      if (nm) localStorage.setItem('pl_name', nm);
      else if (authMode === 'register') localStorage.setItem('pl_name', $('#authName').value.trim() || email.split('@')[0]);
      $('#authMsg').textContent = authMode === 'login' ? 'Bienvenido de nuevo.' : 'Cuenta creada. Bienvenido a la Maison.';
      toast(authMode === 'login' ? 'Bienvenido' : 'Cuenta creada', 'success');
      refreshHeaderAuth();
      setTimeout(() => { renderProfile(); }, 900);
    } catch (e) { $('#authMsg').textContent = e.message; }
    finally { btn.disabled = false; btn.textContent = authMode === 'login' ? 'Entrar' : 'Crear mi cuenta'; }
  };
  setMode('login');
}

// Start
document.addEventListener('DOMContentLoaded', init);

// --- Service Worker registration (optional) ---
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}