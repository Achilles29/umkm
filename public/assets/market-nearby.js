document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-nearby-target]').forEach(button=>{
    button.addEventListener('click',()=>{
      const message=button.querySelector('.shop-nearby-message');
      if(!navigator.geolocation){message.textContent='Lokasi tidak tersedia di perangkat ini.';return;}
      button.disabled=true;message.textContent='Mencari lokasi Anda…';
      navigator.geolocation.getCurrentPosition(position=>{
        const url=new URL(button.dataset.nearbyTarget,location.href);
        url.searchParams.set('lat',position.coords.latitude.toFixed(2));
        url.searchParams.set('lng',position.coords.longitude.toFixed(2));
        url.searchParams.delete('page');
        location.assign(url.toString());
      },()=>{button.disabled=false;message.textContent='Izin lokasi belum tersedia. Aktifkan lokasi lalu coba lagi.';},{enableHighAccuracy:false,timeout:10000,maximumAge:300000});
    });
  });
});
