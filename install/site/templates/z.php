<!doctype html><?= $page->cacheIndicator() ?>
<html lang="<?= $page->lang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= $page->headTitle() ?></title>

  <!-- <base href="<?= $page->baseUrl() ?>"> -->
  <meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1">
  <meta name="generator" content="<?= $page->generator() ?>)">
  <?php snippet('prevnextlinks', ['args' => "type:'header-links'"]) ?>
  <?php /* snippet('favicon') */ ?>

  <?= $page->headInjections() ?>
</head>

<body id='pfy' class='pfy-default-styling pfy-auto-tabulator <?= $page->bodyTagClasses() ?>' <?= $page->bodyTagAttributes() ?>>

<div class='pfy-page'>

  <div class="pfy-header-wrapper">

    <header class='pfy-header v-align-with-nav'>
    <?= $page->homeLink() ?>
    <?php /* snippet('logo', ['args' => 'src:~assets/images/logo[50].jpg']) */ ?>

    </header>

    <?php snippet('nav', ['args' => 'type:top' ]); ?>

    <?php /* snippet('prevnextlinks', ['args' => "'wrapperClass':'pfy-large-screen-only pfy-full-width'"]) */ ?>

  </div><!-- /pfy-header-wrapper -->



  <div class="pfy-main-wrapper">

    <!-- === page content =============== -->
    <main id='main' class='pfy-main'>

      <?= $page->pageContent() ?>

    </main>
    <!-- === /page content =============== -->



    <footer class="pfy-footer">

      <div class="pfy-footer-sitemap">

        <?php snippet('nav', ['args' => 'type:sitemap']); ?>

        <?php snippet('prevnextlinks', ['args' => "'wrapperClass':'pfy-full-width', center:'%loginButton%'"]) ?>

      </div>

<?php if ($l = $page->localhost()->value): ?>

      <div class="dev-footer pfy-localhost-only">
        <div>
          <?= $page->adminPanelLink() ?>
        </div>
        <div>
          <!-- <?= $page->loggedIn() ?> <?= $page->loginButton() ?> -->
        </div>
        <div>
          [<?php snippet('link', ['args' => "url: '~page/?dev=reset', text: 'dev auto'"]) ?> |
          <?php snippet('link', ['args' => "url: '~page/?dev=false', text: 'dev off'"]) ?> |
          <?php snippet('link', ['args' => "url: '~page/?reset', text: 'reset'"]) ?>]
        </div>
      </div>
<?php endif ?>
    </footer>



  <?= $page->smallScreenHeader() ?>

  </div><!-- /pfy-main-wrapper -->

</div><!-- /.pfy-page -->

<?= $page->bodyEndInjections() ?>
</body>
</html>

