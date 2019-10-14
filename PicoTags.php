<?php

class PicoTags extends AbstractPicoPlugin
{
    // const API_VERSION = 2;

    protected $enabled = false;
    protected $tag = '';
    protected $filtered_pages = [];
    protected $search_area = '';
    protected $config = [
        'tag_page' => 'tag',
    ];

    /**
     * Register the "Tags" and "Filter" meta header fields.
     *
     * @see    Pico::getMetaHeaders()
     * @param  array<string> &$headers list of known meta header fields
     * @return void
     */
    public function onMetaHeaders(&$headers)
    {
        $headers['tags'] = 'Tags';
        $headers['filter'] = 'Filter';
    }

    /**
     * Parse the current page's tags and/or filters into arrays.
     *
     * @see    Pico::getFileMeta()
     * @param  array &$meta parsed meta data
     * @return void
     */
    public function onMetaParsed(&$meta)
    {
        $meta['tags'] = $this->parse_tags($meta['tags']);
        $meta['filter'] = $this->parse_tags($meta['filter']);
    }

    public function onConfigLoaded(&$settings)
	{
		if (isset($settings['tag_page']))
			$this->config['tag_page'] = $settings['tag_page'];
	}

    /**
     * If the current page has a filter on tags, filter out the $pages array to
     * only contain pages having any of those tags.
     *
     * @see    Pico::getPages()
     * @see    Pico::getCurrentPage()
     * @see    Pico::getPreviousPage()
     * @see    Pico::getNextPage()
     * @param  array &$pages        data of all known pages
     * @param  array &$currentPage  data of the page being served
     * @param  array &$previousPage data of the previous page
     * @param  array &$nextPage     data of the next page
     * @return void
     */
    public function onPagesLoaded(&$pages, &$currentPage, &$previousPage, &$nextPage)
    {
        if ($currentPage && !empty($currentPage['meta']['filter'])) {
            $tagsToShow = $currentPage['meta']['filter'];

            $this->filtered_pages = $this->tag_filter($pages, $tagsToShow);
        }
        elseif ($this->tag) {
            $this->filtered_pages = $this->tag_filter($pages, [$this->tag]);
        }
    }

    public function onRequestFile(&$file)
    {
        if ($this->tag) {
            $pico = $this->getPico();

            // Aggressively strip out any ./ or ../ parts from the search area before using it
            // as the folder to look in. Should already be taken care of previously, but just
            // as a safeguard to make sure nothing slips through the cracks.
            if ($this->search_area) {
                $folder = str_replace('\\', '/', $this->search_area);
                $folder = preg_replace('~\.+/~', '', $folder);
            }
            else {
                $folder = '';
            }

            $temp_file = $pico->getConfig('content_dir') . ($folder ?: '') . $this->config['tag_page'] . $pico->getConfig('content_ext');
            if (file_exists($temp_file)) {
                $file = $temp_file;
            }
        }
    }

    public function onPageRendering(&$templateName, &$twigVariables)
	{
		$twigVariables['filtered_pages'] = $this->filtered_pages;
        $twigVariables['tag_page'] = $this->config['tag_page'];
        $twigVariables['current_tag'] = $this->tag;
	}

    public function onTwigRegistration()
	{
        $pico = $this->getPico();
        $twig = $pico->getTwig();

		// $twig->addFilter(new \Twig_SimpleFilter('paginate', array($this, 'paginkate')));
        $twig->addFilter(new Twig_SimpleFilter('tag_filter', [$this, 'tag_filter'], ['is_variadic' => true]));
        $twig->addFilter(new Twig_SimpleFilter('parse_tags', [$this, 'parse_tags']));
	}

	public function tag_filter($pages, array $tags = [])
	{
        if (empty($tags)) {
            $currentPage = $this->getCurrentPage();
            $tags = $currentPage['meta']['filter'];
        }
        elseif (is_string($tags)) {
            $tags = [$tags];
        }

        return array_filter($pages, function($page) use ($tags) {
            return array_intersect($tags, $this->parse_tags($page['meta']['tags'])) ? true : false;
        });
	}

    /**
     * Get array of tags from metadata string.
     *
     * @param $tags
     * @return array
     */
    public function parse_tags($tags)
    {
        if (is_array($tags)) {
            return $tags;
        }

        if (!is_string($tags) || mb_strlen($tags) <= 0) {
            return array();
        }

        $tags = explode(',', $tags);

        return is_array($tags) ? array_map('trim', $tags) : array();
    }

    public function onRequestUrl(&$url)
	{
		// checks for tag # in URL
        $pattern = '~^(.+/)?' . $this->config['tag_page'] . '/([^/]+)(/.+)?$~';
        
		if (preg_match($pattern, $url, $matches)) {
            $this->tag = urldecode($matches[2]);

            if (!empty($matches[1])) {
                $this->search_area = $matches[1];
            }
		}
	}
}
