<!doctype html><?= $cacheIndicator ?>
<html lang="<?= $lang ?>">
<head>
  <meta charset="utf-8">
  <title><?= $headTitle ?></title>

  <base href="<?= $baseUrl ?>">
  <meta name="viewport" content="width=device-width, user-scalable=yes, initial-scale=1">
  <meta name="generator" content="<?= $generator ?>">
  <?php snippet('prevnextlinks', ['args' => "type:'header-links'"]) ?>
<?= $favicon ?>

<?= $headInjections ?>
</head>

<body id='pfy' class='pfy-default-styling pfy-auto-tabulator <?= $bodyTagClasses ?>' <?= $bodyTagAttributes ?>>

<div class='pfy-page'>

  <div class="pfy-header-wrapper">

    <header class='pfy-header'>
      <div class="pfy-large-screen-only v-align-with-nav">Title defined in Template</div>
    </header>

    <div class="pfy-nav-outer-wrapper">
         <?php snippet('nav', ['args' => "type:top, wrapperClass: 'pfy-nav-top-right-aligned pfy-nav-colored pfy-mobile-nav-colored'" ]); ?>
    </div>

  </div><!-- /pfy-header-wrapper -->

  <?php snippet('prevnextlinks', ['args' => "'wrapperClass':'pfy-large-screen-only pfy-full-width'"]) ?>



  <div class="pfy-main-wrapper">

    <!-- === page content =============== -->
    <main id='main' class='pfy-main'>

        <?php snippet('field', ['place' => 'before']) ?>

        <?= $pageContent ?>

        <?php snippet('field', ['place' => 'after']) ?>

    </main>
    <!-- === /page content =============== -->



    <footer class="pfy-footer">

      <div class="pfy-footer-sitemap pfy-full-width">

        <?php snippet('nav', ['args' => 'type:sitemap']); ?>

        <?php snippet('prevnextlinks', ['args' => "'wrapperClass':'pfy-full-width', center:'%loginButton%'"]) ?>

      </div>

<?php if ($localhost): ?>

      <div class="dev-footer">
        <div>
          <?= $adminPanelLink ?>
        </div>
        <div>
        </div>
        <div>
          [<?php snippet('link', ['args' => "url: '~page/?dev=reset', text: 'dev auto'"]) ?> |
           <?php snippet('link', ['args' => "url: '~page/?dev=false', text: 'dev off'"]) ?> |
          <?php snippet('link', ['args' => "url: '~page/?reset', text: 'reset'"]) ?>]
        </div>
      </div>
<?php endif ?>
    </footer>

    <?php snippet('countvisits', ['args' => "show:loggedin|localhost, prefix:Visits:, suffix:(since %since%)"]) ?>

    <?= $smallScreenHeader ?>


  </div><!-- /pfy-main-wrapper -->

</div><!-- /.pfy-page -->

<?= $bodyEndInjections ?>
</body>
</html>

