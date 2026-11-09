(function(){
    var sidebar=document.getElementById('admin-sidebar');
    var backdrop=document.getElementById('admin-sidebar-backdrop');
    var bottom=document.getElementById('admin-bottom-menu');
    var top=document.getElementById('admin-menu-toggle');
    if(!sidebar||!backdrop||!bottom)return;
    function setOpen(open){
        sidebar.classList.toggle('is-open',open);
        backdrop.hidden=!open;
        document.body.classList.toggle('admin-menu-open',open);
        bottom.setAttribute('aria-expanded',open?'true':'false');
        if(top)top.setAttribute('aria-expanded',open?'true':'false');
    }
    bottom.addEventListener('click',function(){setOpen(!sidebar.classList.contains('is-open'))});
    if(top)top.addEventListener('click',function(){setOpen(!sidebar.classList.contains('is-open'))});
    backdrop.addEventListener('click',function(){setOpen(false)});
    document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false)});
    window.addEventListener('resize',function(){if(window.innerWidth>960)setOpen(false)});
})();
