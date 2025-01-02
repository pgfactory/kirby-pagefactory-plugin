<!doctype html>
<html lang="<?= page()->lang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= page()->headTitle() ?></title>

  <!-- <base href="<?= page()->baseUrl() ?>"> -->
  <meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1">
  <meta name="generator" content="<?= page()->generator() ?>)">
  <?php snippet('prevnextlinks', ['args' => "type:'header-links'"]) ?>
  <?php snippet('favicon') ?>

  <?= page()->headInjections() ?>
</head>

<body id='pfy' class='pfy-default-styling pfy-auto-tabulator <?= page()->bodyTagClasses() ?>' <?= page()->bodyTagAttributes() ?>>

<div class='pfy-page'>

  <div class="pfy-header-wrapper">

    <header class='pfy-header v-align-with-nav'>
    <?= page()->homeLink() ?>

    </header>


    <?php // snippet('lorem', ['args' => 'min:1, max:10' ]); ?>

    <?php snippet('nav', ['args' => 'type:top' ]); ?>

    <?php snippet('prevnextlinks', ['args' => "'wrapperClass':'pfy-large-screen-only pfy-full-width'"]) ?>

  </div><!-- /pfy-header-wrapper -->



  <div class="pfy-main-wrapper">

    <!-- === page content =============== -->
    <main id='main' class='pfy-main'>

      <?= page()->pageContent() ?>

    </main>
    <!-- === /page content =============== -->



    <footer class="pfy-footer">

      <div class="pfy-footer-sitemap">

        <?php snippet('nav', ['args' => 'type:sitemap' ]); ?>

        <?php snippet('prevnextlinks', ['args' => "'wrapperClass':'pfy-full-width', center:'%loginButton%'"]) ?>

      </div>

<?php if (page()->localhost()): ?>

      <div class="dev-footer pfy-localhost-only">
        <div>
          <?= page()->adminPanelLink() ?>
        </div>
        <div>
          <!-- <?= page()->loggedIn() ?> <?= page()->loginButton() ?> -->
        </div>
        <div>
          [<?php snippet('link', ['args' => "url: '~page/?debug=reset', text: 'debug auto'"]) ?>
           <?php snippet('link', ['args' => "url: '~page/?debug=false', text: 'debug off'"]) ?>]
        </div>
      </div>
<?php endif ?>
    </footer>



  <?= page()->smallScreenHeader() ?>

  </div><!-- /pfy-main-wrapper -->

</div><!-- /.pfy-page -->

<?= page()->bodyEndInjections() ?>
</body>
</html>

