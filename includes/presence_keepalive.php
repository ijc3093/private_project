<script>
(function(){
  setInterval(() => {
    fetch('/Business_only3/ajax/user_presence_ping.php', {
      cache: 'no-store',
      credentials: 'include'
    }).catch(()=>{});
  }, 20000);
})();
</script>
