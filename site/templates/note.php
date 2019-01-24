<?php snippet('header') ?>

<div class="note">

  <div class="note__header">
    <h1 class="title"><?php echo $page->title()->html() ?></h1>
    <div class="autoid" style="text-align: center;">
        <?php echo $page->autoid() ?>
    </div>
  </div>

  <?php echo $page->text()->kirbytext() ?>

</div>

<div class="note">

  <div class="note__header">
    <h1 class="title"><?php echo $page->title()->html() ?></h1>
    <div class="autoid" style="text-align: center;">
        <?php echo $page->autoid() ?>
    </div>
  </div>

  <div id="app">
    {{ info }}
  </div>

</div>

<script>
  const csrf = "<?= csrf() ?>";

  new Vue({
    el: '#app',
    data () {
      return {
        info: null
      }
    },
    mounted () {
      axios
        .get(
          'https://zettelkasten.test/api/pages/test',
          { headers: { "X-CSRF" : csrf }}
        )
        .then(response => (this.info = response.data.data.content.text))
    }
  })
</script>
