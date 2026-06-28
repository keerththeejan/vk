document.addEventListener('DOMContentLoaded', function () {
  const tableBody = document.querySelector('#products-table tbody');
  const searchEl = document.getElementById('product-search');
  const filterCategory = document.getElementById('filter-category');
  const filterStatus = document.getElementById('filter-status');
  const paginationEl = document.getElementById('pagination');
  const statTotal = document.getElementById('stat-total');
  const statLow = document.getElementById('stat-low');
  const statOut = document.getElementById('stat-out');
  const statValue = document.getElementById('stat-value');
  const bulkSelectAll = document.getElementById('bulk-select-all');

  let page = 1, limit = 25, total = 0;

  async function fetchProducts() {
    const q = encodeURIComponent(searchEl.value || '');
    const category = encodeURIComponent(filterCategory.value || '');
    const status = encodeURIComponent(filterStatus.value || '');
    const res = await fetch(`/vk/api/products_list.php?q=${q}&status=${status}&limit=${limit}&page=${page}&category=${category}`);
    if (!res.ok) return showEmpty('Failed to load products');
    const js = await res.json();
    total = js.total ?? (js.data ? js.data.length : 0);
    renderProducts(js.data || []);
    renderPagination(js.page || page, js.limit || limit, total);
    updateStats(js.data || []);
  }

  function showEmpty(message) {
    tableBody.innerHTML = `<tr><td colspan="10" class="vk-empty-state"><h3>No products found</h3><div class="small text-muted">${escapeHtml(message || 'No products match your filters.')}</div><div class="mt-3"><a href="/vk/modules/products/add.php" class="btn btn-primary">+ Add First Product</a></div></td></tr>`;
  }

  function updateStats(rows) {
    // Lightweight stats from current page; full stats should come from an API for accuracy
    const totals = { total: total, low: 0, out: 0, value: 0 };
    rows.forEach(r => {
      const stock = Number(r.opening_stock || 0);
      const price = Number(r.selling_price || r.price || 0);
      if (stock <= (r.low_stock_threshold ?? 5) && stock > 0) totals.low++;
      if (stock <= 0) totals.out++;
      totals.value += stock * price;
    });
    statTotal.textContent = String(totals.total);
    statLow.textContent = String(totals.low);
    statOut.textContent = String(totals.out);
    statValue.textContent = `$${Number(totals.value || 0).toFixed(2)}`;
  }

  function renderProducts(products) {
    tableBody.innerHTML = '';
    if (!products || products.length === 0) return showEmpty();
    products.forEach(p => {
      const tr = document.createElement('tr');
      tr.dataset.id = p.id;
      tr.innerHTML = `
        <td><input class="form-check-input row-select" type="checkbox" value="${p.id}"></td>
        <td><img src="/vk/uploads/${p.id}.jpg" alt="" onerror="this.src='/vk/assets/images/placeholder.png'" width="56"></td>
        <td>
          <div class="fw-semibold">${escapeHtml(p.name)}</div>
          <div class="small text-muted">${escapeHtml(p.short_description || '')}</div>
        </td>
        <td class="text-muted">${escapeHtml(p.sku||'')}</td>
        <td class="text-muted">${escapeHtml(p.category_name||'—')}</td>
        <td class="text-muted">${escapeHtml(p.brand_name||'—')}</td>
        <td class="text-end">$${Number(p.selling_price||0).toFixed(2)}</td>
        <td class="text-end">${Number(p.opening_stock||0)}</td>
        <td>${p.status === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">' + escapeHtml(p.status||'') + '</span>'}</td>
        <td class="text-end"><div class="btn-group btn-group-sm" role="group"><a class="btn btn-outline-light" href="/vk/modules/products/view.php?id=${p.id}">View</a><a class="btn btn-outline-primary" href="/vk/modules/products/edit.php?id=${p.id}">Edit</a></div></td>
      `;
      tableBody.appendChild(tr);
    });

    // attach select handlers
    document.querySelectorAll('.row-select').forEach(cb => cb.addEventListener('change', updateBulkSelection));
  }

  function updateBulkSelection() {
    const checked = document.querySelectorAll('.row-select:checked').length;
    bulkSelectAll.checked = (checked > 0 && checked === document.querySelectorAll('.row-select').length);
  }

  function renderPagination(currentPage, limit, totalCount) {
    const pages = Math.max(1, Math.ceil((totalCount || 0) / limit));
    paginationEl.innerHTML = '';
    for (let i = 1; i <= pages; i++) {
      const li = document.createElement('li');
      li.className = 'page-item ' + (i === currentPage ? 'active' : '');
      li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
      li.addEventListener('click', (e) => { e.preventDefault(); page = i; fetchProducts(); });
      paginationEl.appendChild(li);
    }
  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=> ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // events
  searchEl.addEventListener('input', debounce(() => { page = 1; fetchProducts(); }, 300));
  filterCategory.addEventListener('change', () => { page = 1; fetchProducts(); });
  filterStatus.addEventListener('change', () => { page = 1; fetchProducts(); });
  document.getElementById('reset-filters').addEventListener('click', (e) => { e.preventDefault(); searchEl.value=''; filterCategory.value=''; filterStatus.value=''; page=1; fetchProducts(); });

  bulkSelectAll.addEventListener('change', function(){ document.querySelectorAll('.row-select').forEach(i=>i.checked = bulkSelectAll.checked); });
  document.getElementById('bulk-delete').addEventListener('click', function(){
    const ids = Array.from(document.querySelectorAll('.row-select:checked')).map(i=>i.value);
    if (!ids.length) return alert('Select at least one product');
    if (!confirm('Delete selected products? This cannot be undone.')) return;
    // simple POST delete to API (api/product_delete.php) - adapt as needed
    fetch('/vk/api/product_delete.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'ids='+encodeURIComponent(ids.join(','))})
      .then(r=>r.json()).then(j=>{ if (j.success) { fetchProducts(); } else alert(j.error || 'Delete failed'); }).catch(()=>alert('Delete failed'));
  });

  function debounce(fn, wait){ let t; return function(){ clearTimeout(t); t = setTimeout(()=>fn.apply(this, arguments), wait); } }

  // initial load
  fetchProducts();
});
