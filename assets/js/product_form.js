document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('product-form');
  const gen = document.getElementById('gen-sku');
  const nameInput = document.getElementById('name');
  const skuInput = document.getElementById('sku');

  gen.addEventListener('click', function(){
    const name = nameInput.value.trim() || 'PROD';
    skuInput.value = generateSku(name);
  });

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    const res = await fetch('/vk/api/products_save.php', {method:'POST', body: fd});
    const json = await res.json();
    if (json.success) {
      window.location.href = '/vk/modules/products/view.php?id=' + json.id;
    } else {
      alert(json.error || 'Save failed');
    }
  });

  function generateSku(name){
    const a = name.replace(/[^A-Za-z0-9]+/g,'').toUpperCase().slice(0,6);
    const rand = Math.floor(Math.random()*9000)+1000;
    return a + '-' + rand;
  }
});
