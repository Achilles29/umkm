(function(){
  document.querySelectorAll('[data-photo-picker]').forEach(function(input){
    var preview=input.closest('form').querySelector('.photo-picker-preview');
    if(!preview)return;
    var urls=[];
    input.addEventListener('change',function(){
      urls.forEach(URL.revokeObjectURL);urls=[];preview.replaceChildren();input.setCustomValidity('');
      var files=Array.from(input.files||[]);if(!files.length)return;
      var max=Number(input.dataset.max||8);
      if(files.length>max){input.setCustomValidity('Pilih paling banyak '+max+' foto.');input.reportValidity();return;}
      for(var i=0;i<files.length;i++){
        var file=files[i];
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024){
          input.setCustomValidity('Gunakan JPG, PNG, atau WebP maksimal 5 MB per foto.');input.reportValidity();preview.replaceChildren();urls.forEach(URL.revokeObjectURL);urls=[];return;
        }
        var figure=document.createElement('figure');var img=document.createElement('img');var caption=document.createElement('figcaption');
        var url=URL.createObjectURL(file);urls.push(url);img.src=url;img.alt='Pratinjau foto '+(i+1);
        caption.textContent=i===0?'Sampul awal · '+file.name:file.name;
        figure.appendChild(img);figure.appendChild(caption);preview.appendChild(figure);
      }
    });
  });
})();
