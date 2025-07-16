
// Sidebar toggle capitole
document.querySelectorAll('.book-FullTitle').forEach(function(bt){
    bt.addEventListener('click', function(){
        this.classList.toggle('open');
        const ul = this.nextElementSibling;
        if(ul) ul.style.display = this.classList.contains('open') ? 'block' : 'none';
    });
});

// Modal pentru CrossReference 
function closeXref(){document.getElementById('xrefModal').style.display='none';}

document.querySelectorAll('.xref').forEach(function(el){
    el.addEventListener('click', function(){
        const cr = this.dataset.cr;
        fetch('bible-reader.php?cr='+encodeURIComponent(cr))
            .then(r=>r.text())
            .then(html=>{
                document.getElementById('xrefContent').innerHTML = html;
                document.getElementById('xrefModal').style.display='block';
            })
            .catch(()=>{
                document.getElementById('xrefContent').innerHTML='Eroare la încărcare';
                document.getElementById('xrefModal').style.display='block';
            });
    });
});