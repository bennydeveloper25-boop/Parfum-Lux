const API = 'http://localhost:8000/api';
const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];
const money = n => { try { return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO', maximumFractionDigits: 0 }).format(n); } catch { return 'C$ ' + Number(n).toLocaleString(); } };
const token = () => localStorage.getItem('pl_admin_token') || '';
function toast(m) { const t = document.createElement('div'); t.className = 'toast'; t.textContent = m; $('#toasts').appendChild(t); setTimeout(() => t.remove(), 3000); }
async function api(path, opts = {}) {
  const { headers: optH, ...rest } = opts;
  const headers = { 'Content-Type': 'application/json', ...(token() ? { Authorization: 'Bearer ' + token() } : {}), ...(optH || {}) };
  let r;
  try {
    r = await fetch(API + path, { ...rest, headers });
  } catch { throw new Error('Sin conexión con la API (¿servidor :8000 apagado?).'); }
  let d = {};
  try { d = await r.json(); }
  catch { throw new Error(`Respuesta no válida del servidor (HTTP ${r.status}). Mira la consola del backend.`); }
  if (!r.ok) throw new Error(d?.error?.message || ('HTTP ' + r.status));
  if (!d || !('data' in d)) throw new Error('Respuesta inesperada del servidor (sin campo data).');
  return d.data;
}
$('#apiLabel').textContent = API;

// Login
if (token()) { enter(); } else { $('#loginView').hidden = false; }
$('#loginForm').addEventListener('submit', async e => {
  e.preventDefault();
  $('#loginErr').textContent = '';
  try {
    const d = await api('/auth/login', { method: 'POST', body: JSON.stringify({ email: $('#loginEmail').value, password: $('#loginPass').value }) });
    if (d.user?.role !== 'admin') { $('#loginErr').textContent = 'Ese usuario no es admin.'; return; }
    localStorage.setItem('pl_admin_token', d.token);
    localStorage.setItem('pl_admin_user', d.user.email);
    enter();
  } catch (err) { $('#loginErr').textContent = err.message; }
});
function enter() { $('#loginView').hidden = true; $('#app').hidden = false; $('#adminUser').textContent = localStorage.getItem('pl_admin_user') || ''; loadAll(); }
$('#logoutBtn').onclick = () => { localStorage.removeItem('pl_admin_token'); location.reload(); };
$('#viewShopBtn').onclick = () => window.open('../index.html', '_blank');
$('#menuBtn').onclick = () => $('#sidebar').classList.toggle('open');
$('#refreshBtn').onclick = () => loadAll();

// Nav
$$('#sideNav button').forEach(b => b.onclick = () => {
  $$('#sideNav button').forEach(x => x.classList.remove('active')); b.classList.add('active');
  $$('.view').forEach(v => v.hidden = true);
  $('#view-' + b.dataset.view).hidden = false;
  $('#crumb').textContent = b.textContent.trim();
  $('#sidebar').classList.remove('open');
});

async function loadAll() { await Promise.allSettled([loadStats(), loadProducts(), loadOrders(), loadReviews(), loadContacts(), loadFaqs(), loadFamilies(), loadUsers()]); }

// Dashboard
async function loadStats() {
  const s = await api('/admin/stats');
  $('#statCards').innerHTML = [
    ['Productos', s.products], ['Pedidos', s.orders], ['Ingresos', money(s.revenue)],
    ['Stock uds.', s.stock], ['Clientes', s.customers], ['Mensajes sin leer', s.contacts],
  ].map(([k, v]) => `<div class="card"><b>${v}</b><span>${k}</span></div>`).join('');
  const mc = $('#navMsgCount'); mc.hidden = !s.contacts; mc.textContent = s.contacts;
  $('#lowStock').innerHTML = s.lowStock.length ? s.lowStock.map(v => `<div>${v.product_name} · ${v.size_ml}ml — <b>${v.stock}</b> uds.</div>`).join('') : '<p class="muted">Sin alertas.</p>';
  $('#recentTable tbody').innerHTML = s.recent.map(o => `<tr><td>#${o.id}</td><td>${(o.created_at || '').slice(0, 10)}</td><td>${money(o.total)}</td><td><span class="badge ${o.status}">${o.status}</span></td><td>${o.items}</td></tr>`).join('');
  drawSales(s.daily || []);
}
function drawSales(daily) {
  const c = $('#chartSales'); const ctx = c.getContext('2d');
  const W = c.width = c.offsetWidth || 600, H = c.height = 180;
  ctx.clearRect(0, 0, W, H);
  const vals = daily.map(d => Number(d.t)); if (!vals.length) { ctx.fillText('Sin datos', 10, 20); return; }
  const max = Math.max(...vals, 1);
  ctx.beginPath();
  vals.forEach((v, i) => { const x = (i / Math.max(vals.length - 1, 1)) * (W - 20) + 10; const y = H - 20 - (v / max) * (H - 40); i ? ctx.lineTo(x, y) : ctx.moveTo(x, y); });
  ctx.strokeStyle = '#6D7AA8'; ctx.lineWidth = 2; ctx.stroke();
}

// Products
let familiesCache = [];
async function loadProducts() {
  const [rows, fams] = await Promise.all([api('/products?limit=100'), api('/families').catch(() => [])]);
  familiesCache = fams || [];
  $('#productsTable tbody').innerHTML = rows.map(p => `<tr>
    <td>${p.image_url ? `<img class="thumb" src="${p.image_url}" alt="">` : ''}</td>
    <td>${p.sku}</td><td><b>${p.name}</b><br><small class="muted">${p.tagline || ''}</small></td>
    <td>${p.family_name || ''}</td><td>${money(p.price_base)}</td>
    <td>${p.is_featured ? 'Sí' : 'No'}</td>
    <td><button class="btn sm" data-edit="${p.id}">Editar</button> <button class="btn sm danger" data-del="${p.id}">Borrar</button></td></tr>`).join('');
  $$('#productsTable [data-edit]').forEach(b => b.onclick = () => productForm(Number(b.dataset.edit)));
  $$('#productsTable [data-del]').forEach(b => b.onclick = async () => {
    if (!confirm('¿Borrar producto #' + b.dataset.del + '?')) return;
    await api('/products/' + b.dataset.del, { method: 'DELETE' }); toast('Borrado'); loadProducts(); loadStats();
  });
}
$('#newProductBtn').onclick = () => productForm(0);
async function productForm(id) {
  let p = { sku: '', name: '', tagline: '', description: '', family_id: '', concentration: 'Eau de Parfum', price_base: 0, is_featured: 0, is_active: 1, image_url: '' };
  if (id) p = await api('/products/' + id);
  openModal('Producto ' + (id ? '#' + id : 'nuevo'), `
    <div class="form-grid">
      <label>SKU<input id="f_sku" value="${p.sku || ''}"></label>
      <label>Nombre<input id="f_name" value="${p.name || ''}"></label>
      <label>Tagline<input id="f_tag" value="${p.tagline || ''}"></label>
      <label>Descripción<textarea id="f_desc">${p.description || ''}</textarea></label>
      <label>Familia<select id="f_fam"><option value="">—</option>${familiesCache.map(f => `<option value="${f.id}" ${p.family_id == f.id ? 'selected' : ''}>${f.name}</option>`).join('')}</select></label>
      <label>Concentración<select id="f_conc">${['Parfum', 'Eau de Parfum', 'Eau de Toilette', 'Extrait'].map(c => `<option ${p.concentration === c ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
      <label>Precio base C$<input id="f_price" type="number" step="0.01" value="${p.price_base || 0}"></label>
      <label>Foto URL<input id="f_img" value="${p.image_url || ''}" placeholder="https://..."></label>
      <label><span><input id="f_feat" type="checkbox" ${p.is_featured ? 'checked' : ''}> Destacado</span></label>
      <button class="btn primary" id="f_save">Guardar</button>
    </div>`);
  $('#f_save').onclick = async () => {
    const body = { sku: $('#f_sku').value, name: $('#f_name').value, tagline: $('#f_tag').value, description: $('#f_desc').value, family_id: $('#f_fam').value ? Number($('#f_fam').value) : null, concentration: $('#f_conc').value, price_base: Number($('#f_price').value), is_featured: $('#f_feat').checked ? 1 : 0, is_active: 1, image_url: $('#f_img').value };
    if (id) await api('/products/' + id, { method: 'PUT', body: JSON.stringify(body) });
    else await api('/products', { method: 'POST', body: JSON.stringify(body) });
    closeModal(); toast('Guardado'); loadProducts(); loadStats();
  };
}

// Orders
async function loadOrders() {
  const st = $('#orderStatusFilter').value;
  const rows = await api('/admin/orders?limit=100' + (st ? '&status=' + st : ''));
  $('#ordersTable tbody').innerHTML = rows.map(o => `<tr><td>#${o.id}</td><td>${(o.created_at || '').slice(0, 16).replace('T', ' ')}</td><td>${money(o.total)}</td><td><span class="badge ${o.status}">${o.status}</span></td><td><button class="btn sm" data-v="${o.id}">Ver</button></td></tr>`).join('');
  $$('#ordersTable [data-v]').forEach(b => b.onclick = () => orderDetail(Number(b.dataset.v)));
}
$('#orderStatusFilter').onchange = loadOrders;
async function orderDetail(id) {
  const o = await api('/orders/' + id);
  const box = $('#orderDetail'); box.hidden = false;
  box.innerHTML = `<h3>Pedido #${o.id} — ${money(o.total)} ${o.currency || 'NIO'}</h3>
    <p>Estado actual: <b>${o.status}</b></p>
    <ul>${(o.items || []).map(i => `<li>${i.qty} × ${i.product_name} ${i.size_ml}ml — ${money(i.unit_price)}</li>`).join('')}</ul>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">${['pending', 'paid', 'preparing', 'shipped', 'delivered', 'cancelled'].map(s => `<button class="btn sm" data-s="${s}">${s}</button>`).join('')}</div>`;
  $$('[data-s]', box).forEach(b => b.onclick = async () => { await api('/orders/' + id, { method: 'PUT', body: JSON.stringify({ status: b.dataset.s }) }); toast('Estado: ' + b.dataset.s); loadOrders(); loadStats(); orderDetail(id); });
  box.scrollIntoView({ behavior: 'smooth' });
}

// Reviews / contacts / faqs / families / users
async function loadReviews() {
  const rows = await api('/admin/reviews');
  $('#reviewsTable tbody').innerHTML = rows.map(r => `<tr><td>${r.product_name}</td><td>${r.author_name}</td><td>${r.rating}</td><td>${r.title || ''}</td><td>${r.is_approved ? 'Sí' : 'No'}</td><td><button class="btn sm" data-t="${r.id}">Cambiar</button></td></tr>`).join('');
  $$('#reviewsTable [data-t]').forEach(b => b.onclick = async () => { await api('/admin/reviews/' + b.dataset.t, { method: 'PUT' }); loadReviews(); });
}
async function loadContacts() {
  const rows = await api('/admin/contacts');
  $('#contactsTable tbody').innerHTML = rows.map(c => `<tr><td>${(c.created_at || '').slice(0, 16).replace('T', ' ')}</td><td>${c.name}</td><td>${c.email}</td><td><b>${c.subject || ''}</b><br>${(c.message || '').slice(0, 80)}</td><td>${c.is_read ? 'Sí' : 'No'}</td><td>${c.is_read ? '' : `<button class="btn sm" data-r="${c.id}">Marcar leído</button>`}</td></tr>`).join('');
  $$('#contactsTable [data-r]').forEach(b => b.onclick = async () => { await api('/admin/contacts/' + b.dataset.r, { method: 'PUT' }); loadContacts(); loadStats(); });
}
async function loadFaqs() {
  const rows = await api('/faqs');
  $('#faqsList').innerHTML = rows.map(f => `<div class="item"><b>${f.question}</b><p class="muted">${f.answer}</p></div>`).join('');
}
async function loadFamilies() {
  const rows = await api('/families');
  $('#familiesList').innerHTML = (rows || []).map(f => `<div class="item"><b>${f.name}</b><p class="muted">${f.description || ''}</p></div>`).join('');
}
async function loadUsers() {
  const rows = await api('/admin/users');
  $('#usersTable tbody').innerHTML = rows.map(u => `<tr><td>${u.name}</td><td>${u.email}</td><td>${u.role}</td><td>${u.is_active ? 'Sí' : 'No'}</td><td>${(u.created_at || '').slice(0, 10)}</td></tr>`).join('');
}

// Modal
function openModal(t, html) { $('#modalTitle').textContent = t; $('#modalBody').innerHTML = html; $('#modal').hidden = false; }
function closeModal() { $('#modal').hidden = true; }
$('#modalClose').onclick = closeModal;
