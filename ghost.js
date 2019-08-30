var users = document.querySelectorAll('.ghost-user-login');
users.forEach(function(i){
   i.addEventListener('click', function(e){

      e.preventDefault();

      var data = [];
      data.push("action=ghost_handler");
      data.push("user=" + e.target.dataset.user);

      var send = data.join('&');

      var ajax = new XMLHttpRequest();
      ajax.open('POST', ajaxurl, true);
      ajax.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      ajax.onload = function(){
          if(ajax.status >= 200 && ajax.status < 400){
              if(ajax.responseText !== 'failed') {
                  window.location.href = ajax.responseText;
              }else{
                  alert('Ghosting Failed');
              }
          }
      };
      ajax.send(send);
   });
});
