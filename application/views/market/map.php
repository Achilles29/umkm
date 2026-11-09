<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Peta Toko — Rembang UMKM</title>
    <link rel="icon" href="<?= base_url('assets/favicon.ico?v=1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/tabler.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/leaflet/leaflet.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/marketplace.css?v=4') ?>">
</head>
<body class="shop-body">
<?php $this->load->view('market/_header'); ?>
<main class="shop-main container-xl">
    <div class="shop-page-title shop-title-row"><div><span class="shop-eyebrow">TEMUKAN DI SEKITARMU</span><h1>Peta toko Rembang</h1><p>Jelajahi area kecamatan dan buka daftar toko di wilayah pilihan.</p></div><a class="shop-map-link" href="<?= site_url('daftar-toko') ?>">Lihat daftar toko →</a></div>
    <form class="shop-area-filter shop-map-filter" method="get" action="<?= site_url('peta') ?>"><label for="district">Kecamatan<select id="district" name="district"><option value="">Semua kecamatan</option><?php foreach ($districts as $district): ?><option value="<?= (int)$district['id'] ?>" <?= $selected_district && $selected_district['id'] == $district['id'] ? 'selected' : '' ?>><?= html_escape($district['name']) ?></option><?php endforeach; ?></select></label><button type="submit">Terapkan</button><?php if ($selected_district): ?><a href="<?= site_url('peta') ?>">Reset</a><?php endif; ?></form>
    <div class="shop-map-shell"><div id="shop-map" role="img" aria-label="Peta area toko Rembang"></div><div class="shop-map-caption"><strong><?= (int)$store_count ?> toko ditampilkan</strong><span>Penanda area menunjukkan pusat wilayah kecamatan, bukan alamat persis toko. Titik toko persis hanya ditampilkan jika pemilik menyetujui publikasi lokasinya.</span></div></div>
</main>
<?php $active_tab='map'; $this->load->view('market/_bottom_nav', array('active_tab' => $active_tab, 'cart_count' => $cart_count)); ?>
<script src="<?= base_url('assets/leaflet/leaflet.js') ?>"></script>
<script>
(function(){
  const areas=<?= json_encode($area_markers, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  const exact=<?= json_encode($exact_markers, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  const map=L.map('shop-map',{scrollWheelZoom:false}).setView([-6.75,111.45],10);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
  const bounds=[];
  for(const area of areas){
    const marker=L.circleMarker([area.lat,area.lng],{radius:12,color:'#fff',weight:3,fillColor:'#2673c7',fillOpacity:.95}).addTo(map);
    const popup=document.createElement('div');const title=document.createElement('strong');title.textContent='Area '+area.name;popup.appendChild(title);
    const detail=document.createElement('p');detail.textContent=area.count+' toko di kecamatan ini';popup.appendChild(detail);
    const link=document.createElement('a');link.href=area.url;link.textContent='Lihat toko →';popup.appendChild(link);
    marker.bindPopup(popup);marker.bindTooltip(area.name);bounds.push([area.lat,area.lng]);
  }
  for(const store of exact){
    const marker=L.circleMarker([store.lat,store.lng],{radius:8,color:'#fff',weight:2,fillColor:'#ef843e',fillOpacity:1}).addTo(map);
    const popup=document.createElement('div');const title=document.createElement('strong');title.textContent=store.name;popup.appendChild(title);
    const detail=document.createElement('p');detail.textContent='Lokasi toko';popup.appendChild(detail);
    const link=document.createElement('a');link.href=store.url;link.textContent='Buka toko →';popup.appendChild(link);
    marker.bindPopup(popup);bounds.push([store.lat,store.lng]);
  }
  if(bounds.length>1)map.fitBounds(bounds,{padding:[28,28],maxZoom:12});
  else if(bounds.length===1)map.setView(bounds[0],12);
})();
</script>
</body></html>
