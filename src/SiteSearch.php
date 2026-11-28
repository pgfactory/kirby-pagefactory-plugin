<?php

namespace PgFactory\PageFactory;

use DOMDocument;
use DOMXPath;

/**
 * Site search over pre-rendered page HTML.
 *
 *   $search = new SiteSearch(['fieldType' => 'icon', 'resultsUrl' => pfyPage('search')->url()]);
 *   echo $search->renderSearchField();
 *   echo $search->renderSearchResults(get('q', ''), $pageContents); // [pageId => html]
 *
 * The caller decides which pages are passed (visibility, language etc.).
 */
class SiteSearch
{
	public const DEFAULTS = [
		// 'input': input field shown immediately; 'icon': search icon that opens the input field
		'fieldType'     => 'input',
		// fieldType 'icon': popover edge aligned with the icon – 'right' (opens towards the left) or 'left'
		'popoverAlign'  => 'right',
		// URL the search field submits to (null → URL of page 'search', or 'search')
		'resultsUrl'    => null,
		'urlArgName'    => 'q',

		// UI texts – pass translated strings as needed
		'label'         => 'Search',
		'placeholder'   => 'Search …',
		'submitText'    => 'Search',
		'openText'      => 'Open search',
		'resultsText'   => '{count} result(s) for “{query}”',
		'noResultsText' => 'No results for “{query}”.',
		'tooShortText'  => 'Please enter at least {min} characters.',

		// matching
		'minLength'     => 2,      // minimum characters per term
		'maxTerms'      => 10,
		'operator'      => 'and',  // 'and': all terms must occur on the page; 'or': any term
		'maxResults'    => 50,
		'excerptLength' => 220,
		// score = hits × weight; 'title' is the page title, 'body' all remaining text
		'weights'       => ['title' => 10, 'h1' => 5, 'h2' => 3, 'h3' => 2, 'body' => 1],

		// elements removed before indexing; supports tag, .class, #id, tag.class, [attr], [attr=value]
		'removeSelectors' => ['script', 'style', '[data-nosearch]'],
		// regex patterns (with delimiters) applied to the HTML before parsing; matches are removed
		'ignore'        => [],
		'excludePages'  => [],

		// inject the small CSS/JS needed for fieldType 'icon' (once per request)
		'injectAssets'  => true,
	];

	protected static bool $assetsInjected = false;
	protected static int $instanceCount = 0;

	protected array $options;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
	{
        $defaults = [
            'label' => TransVars::getVariable('pfy-search-label'),
            'placeholder' => TransVars::getVariable('pfy-search-placeholder'),
            'submitText' => TransVars::getVariable('pfy-search-submitText'),
            'openText' => TransVars::getVariable('pfy-search-openText'),
            'resultsText' => TransVars::getVariable('pfy-search-resultsText'),
            'noResultsText' => TransVars::getVariable('pfy-search-noResultsText'),
            'tooShortText' => TransVars::getVariable('pfy-search-tooShortText'),
        ] + static::DEFAULTS;
		$this->options = array_replace($defaults, $options);
	} // __construct

    /**
     * @param string $key
     * @return mixed
     */
    public function option(string $key): mixed
	{
		return $this->options[$key] ?? null;
	} // option

	// ------------------------------------------------------------------ rendering

    /**
     * @param string|null $query
     * @return string
     */
    public function renderSearchField(?string $query = null): string
	{
		$id        = 'pfy-sitesearch-' . ++static::$instanceCount;
		$urlArgName= $this->options['urlArgName'];
		$query   ??= (string)($_GET[$urlArgName] ?? '');
		$isIcon    = $this->options['fieldType'] === 'icon';
		$action    = $this->options['resultsUrl'] ?? $this->defaultResultsUrl();

		$html = '';

		if ($isIcon && $this->options['injectAssets'] && static::$assetsInjected === false) {
			static::$assetsInjected = true;
		}

		$classes = 'pfy-sitesearch-wrapper';
		if ($isIcon) {
			$classes .= ($this->options['popoverAlign'] === 'left') ? ' pfy-sitesearch--align-left' : ' pfy-sitesearch--align-right';
		} else {
			$classes .= ' pfy-sitesearch--inline'; // field shown right away, no popover
		}

		$html .= '<div class="' . $classes . '">';
		$html .= '<form class="pfy-sitesearch-form" action="' . $this->e($action) . '" method="get" role="search">';

		if ($isIcon) {
			// hidden until JS enhances the widget; without JS the plain field is shown
			$html .= '<button type="button" class="pfy-sitesearch-toggle" aria-expanded="false" aria-controls="' . $id . '-field" title="' . $this->e($this->options['openText']) . '" hidden>'
				. static::icon() . '<span class="visually-hidden">' . $this->e($this->options['openText']) . '</span></button>';
		}

		$html .= '<div class="pfy-sitesearch-field" id="' . $id . '-field">'
			. '<label class="pfy-sitesearch-label visually-hidden" for="' . $id . '">' . $this->e($this->options['label']) . '</label>'
			. '<input type="search" id="' . $id . '" name="' . $this->e($urlArgName) . '" value="' . $this->e($query) . '"'
			. ' placeholder="' . $this->e($this->options['placeholder']) . '" autocomplete="off">'
			. '<button type="submit" class="pfy-sitesearch-submit pfy-button pfy-button-lean">' . $this->e($this->options['submitText']) . '</button>'
			. '</div>';

		$html .= '</form></div>';

		return $html;
	} // renderSearchField


	/**
	 * @param array<string, string> $pageContents pageId => HTML
	 */
	public function renderSearchResults(string $searchStr, array $pageContents): string
	{
		$searchStr = trim($searchStr);

		if ($searchStr === '') {
			return '';
		}

		$terms = $this->terms($searchStr);
		$q     = $this->e($searchStr);

		if ($terms === []) {
			return '<div class="pfy-sitesearch-results"><p class="pfy-sitesearch-message">'
				. $this->text('tooShortText', ['min' => $this->options['minLength']]) . '</p></div>';
		}

		$results = $this->search($searchStr, $pageContents);

		if ($results === []) {
			return '<div class="pfy-sitesearch-results"><p class="pfy-sitesearch-message">'
				. $this->text('noResultsText', ['query' => $q]) . '</p></div>';
		}

		$html  = '<div class="pfy-sitesearch-results" aria-live="polite">';
		$html .= '<p class="pfy-sitesearch-count">' . $this->text('resultsText', ['count' => count($results), 'query' => $q]) . '</p>';
		$html .= '<ol class="pfy-sitesearch-list">';

		foreach ($results as $r) {
			$html .= '<li class="pfy-sitesearch-item">'
				. '<a class="pfy-sitesearch-link" href="' . $this->e($r['url']) . '">' . $this->e($r['title']) . '</a>'
				. ($r['excerpt'] !== '' ? '<p class="pfy-sitesearch-excerpt">' . $r['excerpt'] . '</p>' : '')
				. '</li>';
		}

		return $html . '</ol></div>';
	} // renderSearchResults


	// ------------------------------------------------------------------ searching

	/**
	 * @param array<string, string> $pageContents pageId => HTML
	 * @return list<array{id: string, title: string, url: string, score: float, excerpt: string}> best first
	 */
	public function search(string $searchStr, array $pageContents): array
	{
		$terms = $this->terms($searchStr);

		if ($terms === []) {
			return [];
		}

		$weights    = $this->options['weights'];
		$requireAll = $this->options['operator'] !== 'or';
		$exclude    = (array)$this->options['excludePages'];
		$results    = [];

		foreach ($pageContents as $id => $html) {
			$id = (string)$id;

			if (in_array($id, $exclude, true)) {
				continue;
			}

			$doc   = $this->extract((string)$html);
			$page  = function_exists('page') ? pfyPage($id) : null;
			$title = $page?->title()->value() ?: ($doc['h1'][0] ?? $id);

			$sources = ['title' => [$title]] + array_diff_key($doc, ['text' => true]);

			$score   = 0.0;
			$matched = [];

			foreach ($sources as $kind => $texts) {
				foreach ($texts as $text) {
					$haystack = mb_strtolower($text);
					foreach ($terms as $i => $term) {
						$count = mb_substr_count($haystack, $term);
						if ($count > 0) {
							$matched[$i] = true;
							$score += $count * (float)($weights[$kind] ?? 1);
						}
					}
				}
			}

			if ($score <= 0 || ($requireAll && count($matched) < count($terms))) {
				continue;
			}

			$results[] = [
				'id'      => $id,
				'title'   => $title,
				'url'     => $page?->url() ?? $id,
				'score'   => $score,
				'excerpt' => $this->excerpt($doc['text'], $terms),
			];
		}

		usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

		return array_slice($results, 0, (int)$this->options['maxResults']);
	} // search

	/**
	 * Lowercase terms; "quoted phrases" stay together.
	 *
	 * @return list<string>
	 */
	public function terms(string $searchStr): array
	{
		$searchStr = mb_substr(trim($searchStr), 0, 200);
		$min       = (int)$this->options['minLength'];
		$terms     = [];

		preg_match_all('/"([^"]+)"|(\S+)/u', $searchStr, $matches, PREG_SET_ORDER);

		foreach ($matches as $m) {
			$term = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
			$term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $term) ?? ''));

			if (mb_strlen($term) >= $min) {
				$terms[$term] = $term;
			}
		}

		return array_slice(array_values($terms), 0, (int)$this->options['maxTerms']);
	} // terms

	/**
	 * HTML → ['h1' => [...], 'h2' => [...], 'h3' => [...], 'body' => [text], 'text' => full text]
	 */
	protected function extract(string $html): array
	{
		foreach ((array)$this->options['ignore'] as $pattern) {
			$html = preg_replace($pattern, ' ', $html) ?? $html;
		}

		$dom = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$xpath = new DOMXPath($dom);
		$out = ['h1' => [], 'h2' => [], 'h3' => []];

		foreach (array_keys($out) as $tag) {
			foreach ($xpath->query('//' . $tag) ?: [] as $node) {
				$out[$tag][] = static::normalize($node->textContent);
				// headings are scored by their own weight, not again as body text
				$node->setAttribute('data-sitesearch-heading', '1');
			}
		}

		$body = $dom->getElementsByTagName('body')->item(0);
		$full = $body ? $this->toText($dom->saveHTML($body)) : '';

		foreach ($xpath->query('//*[@data-sitesearch-heading]') ?: [] as $node) {
			$node->parentNode?->removeChild($node);
		}
		$bodyOnly = $body ? $this->toText($dom->saveHTML($body)) : '';

		$out['body'] = [$bodyOnly];
		$out['text'] = $full;

		return $out;
	} // extract


    /**
     * @param string $html
     * @return string
     */
    protected function toText(string $html): string
	{
		$text = preg_replace('/<[^>]+>/u', ' ', $html) ?? $html;
		return static::normalize(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	} // toText


    /**
     * @param string $text
     * @return string
     */
    protected static function normalize(string $text): string
	{
		return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
	} // normalize


	/**
	 * HTML-safe excerpt around the first match, with <mark> highlights.
	 */
	protected function excerpt(string $text, array $terms): string
	{
		if ($text === '') {
			return '';
		}

		$length = (int)$this->options['excerptLength'];
		$total  = mb_strlen($text);
		$pos    = null;

		foreach ($terms as $term) {
			$p = mb_stripos($text, $term);
			if ($p !== false && ($pos === null || $p < $pos)) {
				$pos = $p;
			}
		}

		$pos ??= 0;
		$start = max(0, $pos - intdiv($length, 3));

		if ($start > 0) {
			$space = mb_strpos($text, ' ', $start);
			if ($space !== false && $space < $pos) {
				$start = $space + 1;
			}
		}

		$excerpt = mb_substr($text, $start, $length);

		if ($start + $length < $total) {
			$lastSpace = mb_strrpos($excerpt, ' ');
			if ($lastSpace !== false && $lastSpace > $length / 2) {
				$excerpt = mb_substr($excerpt, 0, $lastSpace);
			}
			$excerpt .= ' …';
		}

		if ($start > 0) {
			$excerpt = '… ' . $excerpt;
		}

		$html = $this->e($excerpt);

		usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
		$alternatives = array_map(fn ($t) => preg_quote($this->e($t), '/'), $terms);

		return preg_replace('/' . implode('|', $alternatives) . '/iu', '<mark>$0</mark>', $html) ?? $html;
	} // excerpt


	// ------------------------------------------------------------------ helpers
    /**
     * @return string
     */
    protected function defaultResultsUrl(): string
	{
		if (function_exists('page') && ($page = pfyPage('search'))) {
			return $page->url();
		}
		return 'search';
	} // defaultResultsUrl


    /**
     * @param string $key
     * @param array $replace
     * @return string
     */
    protected function text(string $key, array $replace): string
	{
		$text = $this->e((string)$this->options[$key]);
		foreach ($replace as $k => $v) {
			// values are escaped by the caller
			$text = str_replace('{' . $k . '}', (string)$v, $text);
		}
		return $text;
	} // text


    /**
     * @param string $value
     * @return string
     */
    protected function e(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	} // e

    /**
     * @return string
     */
    protected static function icon(): string
	{
		return '<svg class="pfy-sitesearch-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">'
			. '<circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/>'
			. '<line x1="15.5" y1="15.5" x2="21" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
	} // icon
} // SiteSearch
