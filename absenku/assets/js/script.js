// Toggle sidebar on small screens
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('toggleSidebar');
  var sidebar = document.getElementById('sidebar');
  if(btn){
    btn.addEventListener('click', function(){
      sidebar.classList.toggle('show');
    });
  }
});