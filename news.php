<?php

#!# Add support for PDF conversion so it can be treated as an image


# Class to create a news management system
class news extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'database' => 'news',
			'table' => 'articles',
			'databaseStrictWhere' => true,
			'administrators' => true,
			'imageDirectory' => NULL,
			'imageLocation' => NULL,	// Equivalent to imageDirectory in URL terms
			'thumbnailsSubfolder' => 'thumbnails/',
			'userCallback' => NULL,		// Callback function
			'divId' => 'newsarticles',
			'h1' => '<h1>News submission</h1>',
			'tabUlClass' => 'tabsflat',
			'imageWidthMain' => 300,
			'imageWidthThumbnail' => 150,
			'headingLevelPortal' => 3,	// Heading level (e.g. 3 for h3) for the news titles
			'headingLevelListing' => 2,	// Heading level (e.g. 2 for h2) for the news titles
			'newsPermalinkUrl' => '/news/',
			'feedPermalinkUrl' => '/news/feed.rss',
			'archivePermalinkUrl' => '/news/previous.html',
			'authentication' => false,	// Defined on a per-action basis below
			'internalHostRegexp' => NULL,
			'feedTitle' => 'News',
			'feedImage' => NULL,
			'formDiv' => 'ultimateform horizontalonly',
			'richtextEditorCSS' => array (),	// URLs of stylesheets to apply to richtext editing
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '',
				'tab' => 'Home',
				'icon' => 'house',
				'authentication' => true,
			),
			'submit' => array (
				'description' => 'Submit news',
				'url' => 'submit.html',
				'tab' => 'Submit news',
				'icon' => 'add',
				'authentication' => true,
			),
			'articles' => array (
				'description' => false,
				'url' => 'articles/',
				'tab' => 'Review articles',
				'icon' => 'pencil',
				'administrator' => true,
			),
			'export' => array (
				'description' => 'Export',
				'url' => 'export/',
				'tab' => 'Export',
				'icon' => 'application_view_list',
				'administrator' => false,
				'authentication' => true,
			),
			'exportformat' => array (	// Used for e.g. AJAX calls, etc.
				'description' => 'Export',
				'url' => 'export/%id.html',
				'export' => true,
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			-- Administrators
			CREATE TABLE IF NOT EXISTS `administrators` (
			  `username` varchar(255) NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  PRIMARY KEY (`username`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='System administrators';
			
			-- Articles
			CREATE TABLE IF NOT EXISTS `articles` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Article #' PRIMARY KEY,
			  `title` varchar(255) NOT NULL COMMENT 'Title of article',
			  `sites` varchar(255) NOT NULL COMMENT 'Site(s)',
			  `photograph` varchar(255) DEFAULT NULL COMMENT 'Image (if available)',
			  `imageCredit` varchar(255) DEFAULT NULL COMMENT 'Image credit (if any)',
			  `articleRichtext` text NOT NULL COMMENT 'Article text, including mention of relevant person'
			  `articleLongerRichtext` text COMMENT 'If necessary, longer full version of article',
			  `url` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Webpage giving more info, if any',
			  `startDatetime` datetime NOT NULL COMMENT 'Date/time to appear on website',
			  `moniker` varchar(255) DEFAULT NULL COMMENT 'Permalink name',
			  `pinnedFrontPage` TINYINT NULL DEFAULT NULL COMMENT 'Pin to top, on front page?',
			  `username` varchar(255) NOT NULL COMMENT 'Submitted by user',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Submission date',
			  UNIQUE KEY `moniker` (`moniker`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
			
			-- Settings
			CREATE TABLE `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key (ignored)' PRIMARY KEY,
			  `sites` text NOT NULL COMMENT 'Sites available, one per line, as moniker,label,URL'
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Settings';
			INSERT INTO `settings` (`id`, `sites`) VALUES (1, 'example,Example');
		";
	}
	
	
	# Define the available formats and their properties
	private $exportFormats = array (
		'frontpage'	=> array (
			'name' => 'Front page listing (HTML)',
			'extension' => 'html',
			'limit' => false,
			'frontpage' => true,
		),
		'json'		=> array (
			'name' => 'Front page listing (JSON)',
			'extension' => 'json',
			'limit' => 5,
			'frontpage' => true,
		),
		'recent'	=> array (
			'name' => 'Recent news full HTML page',
			'extension' => 'html',
			'limit' => 10,
			'frontpage' => false,
		),
		'archive'	=> array (
			'name' => 'Complete archive HTML page',
			'extension' => 'html',
			'limit' => false,
			'frontpage' => false,
		),
		'feed'		=> array (
			'name' => 'RSS feed',
			'extension' => 'rss',
			'limit' => 24,
			'frontpage' => false,
		),
		'feed.atom'		=> array (
			'name' => 'Atom feed',
			'extension' => 'xml',
			'limit' => 24,
			'frontpage' => false,
		),
	);
	
	
	# Additional initialisation, pre-actions
	public function mainPreActions ()
	{
		# Process the sites setting, which is saved as a textarea block
		if ($this->action != 'settings') {
			$sitesSetting = array ();
			$this->siteUrls = array ();
			$lines = explode ("\n", str_replace ("\r\n", "\n", trim ($this->settings['sites'])));
			foreach ($lines as $line) {
				list ($site, $label, $url) = explode (',', $line, 3);
				$sitesSetting[$site] = $label;
				$this->siteUrls[$site] = $url;
			}
			$this->settings['sites'] = $sitesSetting;
		}
		
	}
	
	
	# Additional initialisation
	public function main ()
	{
		# Get the user details
		if (!$this->userDetails = $this->userDetails ()) {
			$requiresAuth = (isSet ($this->actions[$this->action]['authentication']) && $this->actions[$this->action]['authentication']);
			if ($requiresAuth) {	// Use authentication check for authorisation
				$html = "\n<p>You do not seem to be a registered user. Please <a href=\"{$this->baseUrl}/feedback.html\">contact the Webmaster</a> if this is incorrect.</p>";
				echo $html;
				return false;
			}
		}
		
		# Define the photograph directory
		$this->photographDirectoryOriginals = $this->settings['imageDirectory'] . 'originals/';
		$this->photographDirectoryMain = $this->settings['imageDirectory'];	// i.e. top level
		$this->photographDirectoryThumbnail = $this->settings['imageDirectory'] . $this->settings['thumbnailsSubfolder'];
		
	}
	
	
	# Function to get the user details or force them to register
	private function userDetails ()
	{
		# Get the list of users
		$userCallback = $this->settings['userCallback'];
		if (!$userDetails = $userCallback ($this->user)) {
			return false;
		}
		
		# Filter to used fields
		$fields = array ('email', 'forename');
		$userDetails = application::arrayFields ($userDetails, $fields);
		
		# Otherwise return the details
		return $userDetails;
	}
	
	
	# Welcome screen
	public function home ()
	{
		# Start the page
		$html  = "\n\n" . "<p>Welcome, {$this->userDetails['forename']}, to the news submission system.</p>";
		
		# Show moderation links for admins
		if ($this->userIsAdministrator) {
			$html .= "\n<h2>Review article submissions</h2>";
			$html .= $this->moderationList ();
		}
		
		# Show the reporting screen
		$html .= "\n<h2>Submit an item of news</h2>";
		$html .= $this->submissionForm ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Submit page
	public function submit ()
	{
		# Create the report form
		$html = $this->submissionForm ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Submission form
	private function submissionForm ()
	{
		# Start the HTML
		$html = '';
		
		# Determine fields to exclude
		$exclude = array ('username');
		$exclude = array_merge ($exclude, array ('moniker', 'pinnedFrontPage'));	// Manually added as separate dataBinding block at the end
		if (!$this->userIsAdministrator ()) {
			$exclude = array_merge ($exclude, array ('articleLongerRichtext'));
		}
		
		# Create the form
		$form = new form (array (
			'displayDescriptions' => false,
			'databaseConnection' => $this->databaseConnection,
			'div' => $this->settings['formDiv'],
			'enableNativeRequired' => true,
			#!# The part of this confirmation about reviewing does not apply when making live directly by an admin
			'formCompleteText' => 'Thanks for submitting this article. The Webmaster will review it and confirm when it is online.',
			'emailName' => $this->settings['applicationName'],
		));
		
		# Make clear that submissions are moderated
		$form->heading ('p', 'Please note that all submissions are moderated and checked for suitability for publication.');
		
		# Databind the form
		$form->dataBinding ($this->formMainAttributes () + array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'exclude' => $exclude,
			'attributes' => $this->formDataBindingAttributes (),
		));
		$form->email (array (
			'name' => 'email',
			'title' => 'Your e-mail address (purely for acknowledgement)',
			'default' => $this->userDetails['email'],
			'editable' => false,
		));
		if ($this->userIsAdministrator ()) {
			$form->dataBinding ($this->formMainAttributes () + array (
				'database' => $this->settings['database'],
				'table' => $this->settings['table'],
				'includeOnly' => array ('moniker', 'pinnedFrontPage'),
				'attributes' => $this->formDataBindingAttributes (),
			));
		}
		
		# Set to mail the admin
		$form->setOutputEmail ($this->settings['webmaster'], $this->settings['administratorEmail'], 'New news submission from ' . ($this->userName ? $this->userName : $this->user) . ': {title}', NULL, 'email');
		
		# Obtain the result
		if (!$result = $form->process ($html)) {
			return $html;
		}
		
		# Remove fixed data
		unset ($result['email']);
		
		# Fix the username
		$result['username'] = $this->user;
		
		# Wipe the photograph filename, and state 1 if present
		if ($result['photograph']) {
			$result['photograph'] = '1';
		}
		
		# Insert the data
		if (!$this->databaseConnection->insert ($this->settings['database'], $this->settings['table'], $result)) {
			#!# Inform admin
		}
		
		# Get the database ID
		$id = $this->databaseConnection->getLatestId ();
		
		# Rename the image to the database ID number
		if ($result['photograph']) {
			$tempLocation = $this->photographDirectoryOriginals . $this->user . '.jpg';
			$newLocation = $this->photographDirectoryOriginals . $id . '.jpg';
			rename ($tempLocation, $newLocation);
			
			# Make a smaller version of the image
			if (!image::resize ($newLocation, 'jpg', $this->settings['imageWidthMain'], false, $this->photographDirectoryMain . $id . '.jpg')) {
				#!# Inform user/admin if fails
			}
			
			# Make a thumbnail version of the image
			if (!image::resize ($newLocation, 'jpg', $this->settings['imageWidthThumbnail'], false, $this->photographDirectoryThumbnail . $id . '.jpg')) {
				#!# Inform user/admin if fails
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Helper function to define main form attributes
	private function formMainAttributes ()
	{
		# Define the attributes
		$attributes = array (
			'intelligence' => true,
			'size' => 70,
			'int1ToCheckbox' => true,
		);
		
		# Return the attributes
		return $attributes;
	}
	
	
	# Helper function to define the form dataBinding attributes
	private function formDataBindingAttributes ()
	{
		# Define the attributes
		$attributes = array (
			'photograph' => array ('directory' => $this->photographDirectoryOriginals, 'previewLocationPrefix' => $this->settings['imageLocation'], 'forcedFileName' => $this->user, 'allowedExtensions' => array ('jpg'), 'lowercaseExtension' => true, 'thumbnail' => true, 'draganddrop' => true, ),
			#!# Ideally there would be some way to define a set of domain names that are treated as 'internal' so that https://www.example.org/foo/ could be entered rather than /foo/ to avoid external links being created
			'articleRichtext' => array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 300, 'maxlength' => 1000, 'externalLinksTarget' => false, 'config.contentsCss' => $this->settings['richtextEditorCSS'], ),
			'articleLongerRichtext' => array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 300, 'externalLinksTarget' => false, 'config.contentsCss' => $this->settings['richtextEditorCSS'], ),
			'sites' => array ('type' => 'checkboxes', 'values' => $this->settings['sites'], 'separator' => ',', 'defaultPresplit' => true, 'output' => array ('processing' => 'special-setdatatype'), ),
			'startDatetime' => array ('default' => 'timestamp', 'picker' => true, ),
			'url' => array ('placeholder' => 'https://', 'regexp' => '^https?://'),
			'moniker' => array ('heading' => array (2 => 'Approval', 'p' => '<strong>To approve this article, allocate it a simple one-word name</strong>, lower-case, without spaces.<br />This will be used for the article permalink, enabling people to link to this article directly.'), 'regexp' => '^([a-z0-9]+)$', 'size' => 30, 'placeholder' => 'E.g. myarticle', 'prepend' => $this->settings['newsPermalinkUrl'] . '#', ),
			'username' => array ('editable' => false, ),
		);
		
		# Return the attributes
		return $attributes;
	}
	
	
	# Moderation list
	private function moderationList ($months = 3)
	{
		# Start the HTML
		$html = '';
		
		# Get the data
		$query = "SELECT id,title FROM articles WHERE moniker IS NULL AND startDatetime > DATE_SUB(NOW(), INTERVAL {$months} MONTH) ORDER BY id DESC;";
		$data = $this->databaseConnection->getPairs ($query);
		
		# End if none
		if (!$data) {
			$html = "\n<p>{$this->tick} There are currently no articles requiring moderation.</p>";
			return $html;
		}
		
		# Convert to list
		$list = array ();
		foreach ($data as $id => $title) {
			$list[] = "<a href=\"{$this->baseUrl}/articles/{$id}/edit.html\">" . htmlspecialchars ($title) . '</a>';
		}
		$html .= "\n<p>The following article(s), submitted within the last {$months} months, are awaiting review:</p>";
		$html .= application::htmlUl ($list);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to provide a list of export formats
	public function export ()
	{
		# Start the HTML
		$html = "\n<p><strong>This page provides the URLs of each feed, for embedding within pages.</strong></p>";
		
		# Note optional parameters
		$html .= "\n<p>Optional parameters:</p>
		<ul>
			<li><tt>limit=<em>&lt;int&gt;</em></tt> (default as listed below).</li>
			<li><tt>REMOTE_ADDR=<em>&lt;[ip]&gt;</em></tt> IP for forwarding.</li>
			<li><tt>REMOTE_USER=<em>&lt;[userid]&gt;</em></tt> Logged-in user for forwarding.</li>
		</ul>";
		
		# Create the list
		foreach ($this->settings['sites'] as $site => $label) {
			
			# Create the table entries
			$table = array ();
			foreach ($this->exportFormats as $format => $attributes) {
				$title = "<strong>" . htmlspecialchars ($attributes['name']) . ":</strong><br />(" . ($attributes['limit'] ? "Limit: {$attributes['limit']}" : 'No limit') . ')' . ($attributes['frontpage'] ? ' (Frontpage type)' : '');
				$location = "{$this->baseUrl}/export/{$format}.{$attributes['extension']}?site={$site}";
				$phpCode = "<a href=\"{$location}\">{$_SERVER['_SITE_URL']}{$location}</a>";
				$table[$title] = $phpCode;
			}
			
			# Compile the HTML
			$html .= "\n<h3>" . htmlspecialchars ($label) . ':</h3>';
			$html .= application::htmlTableKeyed ($table, array (), true, 'lines', $allowHtml = true, $showColons = false);
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to provide exportable HTML
	public function exportformat ($format = false)
	{
		# End if not a registered export format
		if (!isSet ($this->exportFormats[$format])) {
			#!# 404 header
			include ('sitetech/404.html');
			return false;
		}
		
		# Start the HTML
		$html  = '';
		
		# Determine the site(s)
		$site = (
			   isSet ($_GET['site'])
			&& strlen ($_GET['site'])
			&& (count (array_intersect (explode (',', $_GET['site']), array_keys ($this->settings['sites']))) == count (explode (',', $_GET['site'])))		// All requested site(s) exist in settings
		? $_GET['site'] : false);
		
		# Determine the limit
		$limit = (isSet ($_GET['limit']) && ctype_digit ($_GET['limit']) ? $_GET['limit'] : $this->exportFormats[$format]['limit']);
		
		# Determine the months limit
		$maxMonths = (isSet ($_GET['maxmonths']) && ctype_digit ($_GET['maxmonths']) ? $_GET['maxmonths'] : false);
		
		# If $_GET['REMOTE_ADDR'] is supplied as a query string argument, proxy that through
		$remoteAddr = $_SERVER['REMOTE_ADDR'];
		if (isSet ($_GET['REMOTE_ADDR'])) {
			$remoteAddr = $_GET['REMOTE_ADDR'];
		}
		
		# If $_GET['REMOTE_USER'] is supplied as a query string argument, proxy that through
		$remoteUser = $_SERVER['REMOTE_USER'];
		if (isSet ($_GET['REMOTE_USER'])) {
			$remoteUser = $_GET['REMOTE_USER'];
		}
		
		# Add a link to adding an article
		$delimiter = '@';
		if ($remoteUser) {
			$html .= "\n<p class=\"primaryaction right noprint\"><a href=\"{$_SERVER['_SITE_URL']}{$this->baseUrl}/\" title=\"Go to the news submission form\"><img src=\"/images/icons/add.png\" class=\"icon\" /> Submit news</a></p>";
		}
		
		# Add Atom link if the output type is HTML
		if ($this->exportFormats[$format]['extension'] == 'html') {
			$html .= "\n<p class=\"right\"><a href=\"{$this->settings['feedPermalinkUrl']}\"><img src=\"/images/icons/feed.png\" alt=\"Atom icon\" title=\"RSS feed\" class=\"icon\" /></a></p>";
		}
		
		# Construct the HTML based on the selected format
		$function = 'export' . ucfirst (str_replace ('.', '', $format));
		$html .= $this->{$function} ($site, $limit, $this->exportFormats[$format]['frontpage'], $maxMonths);
		
		# Surround with a div (frontControllerApplication will have stripped the 'div' setting when the export flag is on)
		if ($this->settings['divId']) {
			$html = "\n<div id=\"{$this->settings['divId']}\">\n" . $html . "\n\n</div><!-- /#{$this->settings['divId']} -->";
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to format the articles as an HTML table
	private function exportFrontpage ($site, $limit, $frontpage, $maxMonths_ignored)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles or end
		#!# This needs to be ordered by date,ordering
		if (!$articles = $this->getArticles ($site, $limit, $frontpage)) {
			return "\n<p>There are no items of news at present.</p>";
		}
		
		# Construct an HTML table
		$table = array ();
		foreach ($articles as $id => $article) {
			$table[$id] = array (
				'image'		=> $this->articleImage ($article),
				'article'	=> $this->articleTitle ($article, false) . $this->articleBody ($article, false),
			);
		}
		
		# Compile the HTML
		$html = application::htmlTable ($table, array (), 'news portal', $keyAsFirstColumn = false, false, $allowHtml = true, false, false, false, array (), false, $showHeadings = false);
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to format the table as a listing
	private function exportRecent ($site, $limit, $frontpage, $maxMonths_ignored)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles or end
		if (!$articles = $this->getArticles ($site, $limit, $frontpage)) {
			return "\n<p>There are no items of news at present.</p>";
		}
		
		# Build the HTML
		$html  = '';
		foreach ($articles as $id => $article) {
			$html .= "\n\n<div class=\"newsarticle\">";
			$html .= $this->articleTitle ($article, true);
			$html .= "\n" . $this->articleImage ($article, true);
			$html .= $this->articleBody ($article, true);
			$html .= "\n</div>";
		}
		
		# Add a link to remainder
		$totalArticles = $this->getTotalArticles ();
		if ($totalArticles > $limit) {
			$html .= "\n<hr id=\"browseearlier\" />";
			#!# Ideally this would link to the next in the list
			$html .= "\n<p><a href=\"{$this->settings['archivePermalinkUrl']}\">Browse earlier articles&hellip;</a></p>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to format the table as a listing
	private function exportArchive ($site, $limit, $frontpage, $maxMonths)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles or end
		if (!$articles = $this->getArticles ($site, $limit, $frontpage, $maxMonths)) {
			return "\n<p>There are no items of news.</p>";
		}
		
		# Build the HTML
		$html  = '';
		foreach ($articles as $id => $article) {
			$html .= "\n\n<div class=\"newsarticle\">";
			$html .= $this->articleTitle ($article, true);
			$html .= $this->articleImage ($article, true);
			$html .= $this->articleBody ($article, true);
			$html .= "\n</div>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the articles
	private function getArticles ($sites /* string of single site or comma-separated list of sites */, $limit, $frontpage, $maxMonths = false)
	{
		# Define prepared statement values, and assemble FIND_IN_SET strings
		$preparedStatementValues = array ();
		$sites = explode (',', $sites);
		$findInSets = array ();
		foreach ($sites as $index => $site) {
			$placeholder = 'site_' . $index;
			$preparedStatementValues[$placeholder] = $site;
			$findInSets[] = "FIND_IN_SET(:{$placeholder}, sites)";
		}
		
		# Set the default site to link to
		$firstSite = $sites[0];
		
		# Get the data
		$query = "SELECT
			*,
			CONCAT('{$this->settings['archivePermalinkUrl']}','#',moniker) AS articlePermalink,
			DATE_FORMAT(startDatetime, '%D %M, %Y') AS date
			FROM {$this->dataSource}
			WHERE
				    moniker != '' AND moniker IS NOT NULL
				    AND startDatetime <= NOW()"
				. ($maxMonths ? " AND startDatetime > NOW() - INTERVAL {$maxMonths} MONTH" : '')
				. ' AND (' . implode (' OR ', $findInSets) . ')
			ORDER BY '
				. ($frontpage ? 'pinnedFrontPage DESC, ' : '')		// 1 then NULL
				. "startDatetime DESC, timestamp DESC "
			. ($limit ? "LIMIT {$limit} " : '') .
		';';
		$articles = $this->databaseConnection->getData ($query, $this->dataSource, true, $preparedStatementValues);
		
		# Simplify each URL present in the data for the client site requesting (i.e. chopping the server name part if on the same site); e.g. if site=foo supplied and foo's URL is foo.example.com, then https://foo.example.com/path/ is rewritten to /path/
		$articleRichtextFields = array ('articleRichtext', 'articleLongerRichtext');
		foreach ($articles as $key => $article) {
			
			# URL internal
			$delimiter = '@';
			$articles[$key]['url'] = preg_replace ($delimiter . '^' . addcslashes ('https?://' . $this->siteUrls[$firstSite] . '/', $delimiter) . $delimiter, '/', $article['url']);
			
			# Article text (abbreviated and longer)
			foreach ($articleRichtextFields as $articleRichtextField) {
				
				# Strip server name part
				$articles[$key][$articleRichtextField] = preg_replace ($delimiter . addcslashes (' href="' . 'https?://' . $this->siteUrls[$firstSite] . '/', $delimiter) . $delimiter, ' href="/', $articles[$key][$articleRichtextField]);
				
				# Normalise target="_blank" cases
				$articles[$key][$articleRichtextField] = str_replace (' target="_blank"', '', $articles[$key][$articleRichtextField]);
				$articles[$key][$articleRichtextField] = preg_replace ('@<a([^>]*) href="(https?://)@', '<a\1 target="_blank" href="\2', $articles[$key][$articleRichtextField]);
			}
		}
		
		# Add the primary URL for each article
		foreach ($articles as $key => $article) {
			$articles[$key]['primaryUrl'] = $this->primaryUrl ($article);
		}
		
		// application::dumpData ($articles);
		
		# Return the articles
		return $articles;
	}
	
	
	# Function to get the total number of articles
	private function getTotalArticles ()
	{
		# Get the total
		$total = $this->databaseConnection->getTotal ($this->settings['database'], $this->settings['table']);
		
		# Return the total
		return $total;
	}
	
	
	# Function to compile the article title HTML
	private function articleTitle ($article, $listingMode)
	{
		# Compile the HTML
		$html  = '';
		if ($listingMode) {
			$editLink = ($this->userIsAdministrator ? " <a href=\"{$this->baseUrl}/{$this->settings['table']}/{$article['id']}/edit.html\">Edit #{$article['id']}</a>" : '');
			$html .= "\n\n\n<h{$this->settings['headingLevelListing']} id=\"{$article['moniker']}\"><a class=\"small\" title=\"Permalink\" href=\"{$article['articlePermalink']}\">#</a> " . htmlspecialchars ($article['title']) . "</h{$this->settings['headingLevelListing']}>";
			$html .= "\n<p class=\"articledate\"><em>{$article['date']}</em>{$editLink}</p>";
		} else {
			$html .= "\n<h{$this->settings['headingLevelPortal']}><a href=\"{$article['primaryUrl']}\">" . htmlspecialchars ($article['title']) . "</a></h{$this->settings['headingLevelPortal']}>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to compile the article body HTML
	private function articleBody ($article, $listingMode)
	{
		# Start with the text; in listing mode this is always unabbreviated
		if ($listingMode) {
			$html  = "\n" . ($article['articleLongerRichtext'] ? $article['articleLongerRichtext'] : $article['articleRichtext']);
		} else {
			$html  = "\n" . ($article['articleRichtext'] ? $article['articleRichtext'] : $article['articleLongerRichtext']);
			if ($article['articleLongerRichtext']) {
				$html .= "\n<p><a href=\"{$article['articlePermalink']}\">Read more &hellip;</a></p>";
			}
		}
		
		# In listing mode, add a read more link, favouring internal over external
		if ($listingMode) {
			if ($article['url']) {
				$target = (substr ($article['url'], 0, 1) != '/' ? ' target="_blank"' : '');	// Add target if not local
				$html .= "\n<p><a href=\"{$article['url']}\"" . $target . ">Read more &hellip;</a></p>";
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to compile the article image tag
	public function articleImage ($article, $alignright = false)
	{
		# Obtain the path, or end if none
		if (!$imageLocation = $this->imageLocation ($article)) {return false;}
		
		# Compile the image
		$imageCredit = htmlspecialchars ($article['imageCredit']);
		$html = "<p" . ($alignright ? ' class="right"' : '') . "><a href=\"{$article['primaryUrl']}\"><img src=\"{$imageLocation}\" alt=\"{$imageCredit}\" title=\"{$imageCredit}\" border=\"0\" /></a></p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine the image location
	private function imageLocation ($article, &$width = '', &$height = '')
	{
		# End if none
		if (!$article['photograph']) {return false;}
		
		# End if not readable
		$imageFilename = $article['id'] . '.jpg';
		$file = $this->photographDirectoryMain . $this->settings['thumbnailsSubfolder'] . $imageFilename;
		if (!is_readable ($file)) {return false;}
		
		# Get the width and height
		list ($width, $height, $type, $attr) = getimagesize ($file);
		
		# Assemble the location in URL terms
		$location = $this->settings['imageLocation'] . $imageFilename;
		
		# Return the location
		return $location;
	}
	
	
	# Function to determine the primary link (used for the image and the heading)
	private function primaryUrl ($article)
	{
		# If there is a URL, use that
		if ($article['url']) {
			return $article['url'];
		}
		
		# Otherwise, return the article permalink (basically an anchor in the all-articles mode
		return $article['articlePermalink'];
	}
	
	
	# Admin editing section, substantially delegated to the sinenomine editing component
	public function articles ($attributes = array (), $deny = false, $sinenomineExtraSettings = array ())
	{
		# Get the databinding attributes
		$dataBindingAttributes = $this->formDataBindingAttributes ();
		$sinenomineExtraSettings = $this->formMainAttributes ();
		
		# Order most recent first
		#!# Hacky way, because of the lack of a way to modify $settings in editingTable ()
		$_GET['direction'] = 'desc';
		
		# Delegate to the standard function for editing
		$html = $this->editingTable ($this->settings['table'], $dataBindingAttributes, $this->settings['formDiv'], false, $sinenomineExtraSettings);
		
		# Show the HTML
		echo $html;
	}
	
	
	# JSON output
	private function exportJson ($site, $limit, $frontpage)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles
		$articles = $this->getArticles ($site, $limit, $frontpage);
		
		# Decorate
		foreach ($articles as $id => $article) {
			$articles[$id]['imageHtml'] = $this->articleImage ($article, false);
			$articles[$id]['articleHtml'] = ($article['articleRichtext'] ? $article['articleRichtext'] : $article['articleLongerRichtext']);
		}
		
		# Send the feed
		header ('Content-type: application/json');
		echo json_encode ($articles, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		# Die to prevent any more output
		exit ();
	}
	
	
	# RSS 2.0 feed
	private function exportFeed ($site, $limit, $frontpage)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles
		$articles = $this->getArticles ($site, $limit, $frontpage);
		
		# Define the base page
		$fullBaseUrl = "{$_SERVER['_SITE_URL']}{$this->baseUrl}";
		
		# Build the XML
		#!# The title, id and author/name need to take account of the $site setting
		$xml  = '<' . '?' . 'xml version="1.0" encoding="utf-8"?>';	// Use this syntax to avoid confusing the editor
		$xml .= "\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">";
		$xml .= "\n\t<channel>";
		$xml .= "\n\t\t<title>" . htmlspecialchars ($this->settings['feedTitle']) . ' - ' . htmlspecialchars ($this->settings['sites'][$site]) . '</title>';
		$xml .= "\n\t\t<description>" . htmlspecialchars ($this->settings['feedTitle']) . ' - ' . htmlspecialchars ($this->settings['sites'][$site]) . '</description>';
		$xml .= "\n\t\t<link>{$_SERVER['_SITE_URL']}{$this->settings['feedPermalinkUrl']}</link>";
		$xml .= "\n\t\t<lastBuildDate>" . $this->rfc822Date () . "</lastBuildDate>";
		$xml .= "\n\t\t<pubDate>" . $this->rfc822Date () . "</pubDate>";
		$xml .= "\n\t\t<ttl>1800</ttl>";
		
		# Add each entry
		foreach ($articles as $article) {
			$articleText = ($article['articleRichtext'] ? $article['articleRichtext'] : $article['articleLongerRichtext']);
			$xml .= "\n\t\t<item>";
			$xml .= "\n\t\t\t<title>" . htmlspecialchars ($article['title']) . "</title>";
			$xml .= "\n\t\t\t<description>" . str_replace ("\n", ' ', trim (htmlspecialchars (strip_tags ($articleText)))) . '</description>';
			if ($imageLocation = $this->imageLocation ($article, $width, $height)) {
				$xml .= "\n\t\t\t<media:content xmlns:media=\"http://search.yahoo.com/mrss/\" url=\"{$_SERVER['_SITE_URL']}{$imageLocation}\" medium=\"image\" type=\"image/jpeg\" width=\"{$width}\" height=\"{$height}\" />";	// See: https://stackoverflow.com/questions/483675/images-in-rss-feed
			}
			$xml .= "\n\t\t\t<guid isPermaLink=\"false\">{$_SERVER['_SITE_URL']}{$article['articlePermalink']}</guid>";
			$xml .= "\n\t\t\t<pubDate>" . $this->rfc822Date (strtotime ($article['startDatetime'])) . '</pubDate>';
			$xml .= "\n\t\t</item>\n";
		}
		
		# Close the feed
		$xml .= "\n\t</channel>";
		$xml .= "\n\t</rss>";
		
		# Send the feed
		#!# Header is not working, so has been set in .httpd.conf.extract.txt, though this should not be necessary; see possible reasons at: https://stackoverflow.com/questions/2508718/content-type-not-working-in-php
		header ('Content-Type: application/rss+xml; charset=utf-8');
		echo $xml;
		
		# Die to prevent any more output
		exit ();
	}
	
	
	# RFC-822 Date-time as required by RSS 2.0
	private function rfc822Date ($timestamp = 0)
	{
		if (!$timestamp) {$timestamp = time ();}
		$datetime = date ('r', $timestamp);
		
		# Return the data
		return $datetime;
	}
	
	
	# Atom feed
	private function exportFeedatom ($site, $limit, $frontpage)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles
		$articles = $this->getArticles ($site, $limit, $frontpage);
		
		# Define the base page
		$fullBaseUrl = "{$_SERVER['_SITE_URL']}{$this->baseUrl}";
		
		# Build the XML
		#!# The title, id and author/name need to take account of the $site setting
		$xml  = '<' . '?' . 'xml version="1.0" encoding="utf-8"?>';	// Use this syntax to avoid confusing the editor
		$xml .= "\n<feed xmlns=\"http://www.w3.org/2005/Atom\">";
		$xml .= "\n\t<title>" . htmlspecialchars ($this->settings['feedTitle']) . ' - ' . htmlspecialchars ($this->settings['sites'][$site]) . '</title>';
		$xml .= "\n\t<icon>{$this->settings['feedImage']}</icon>";
		$xml .= "\n\t<link rel=\"self\" href=\"{$_SERVER['_SITE_URL']}{$this->settings['feedPermalinkUrl']}\"/>";
		$xml .= "\n\t<updated>" . $this->rfc3339Date () . "</updated>";
		$xml .= "\n\t<id>{$_SERVER['_SITE_URL']}{$this->settings['newsPermalinkUrl']}</id>";
		$xml .= "\n\t<author>\n\t\t<name>{$_SERVER['_SITE_URL']}{$this->settings['newsPermalinkUrl']}</name>\n\t</author>\n";
		
		# Add each entry
		foreach ($articles as $article) {
			$articleText = ($article['articleRichtext'] ? $article['articleRichtext'] : $article['articleLongerRichtext']);
			$xml .= "\n\t<entry>";
			$xml .= "\n\t\t<title>" . htmlspecialchars ($article['title']) . "</title>";
			$xml .= "\n\t\t<link href=\"{$_SERVER['_SITE_URL']}{$article['articlePermalink']}\"/>";
			$xml .= "\n\t\t<id>{$_SERVER['_SITE_URL']}{$article['articlePermalink']}</id>";
			$xml .= "\n\t\t<updated>" . $this->rfc3339Date (strtotime ($article['startDatetime'])) . '</updated>';
			$xml .= "\n\t\t<summary>" . str_replace ("\n", ' ', trim (htmlspecialchars (strip_tags ($articleText)))) . "</summary>";
			$xml .= "\n\t</entry>\n";
		}
		
		# Close the feed
		$xml .= "\n</feed>";
		
		# Send the feed
		#!# Header is not working, so has been set in .httpd.conf.extract.txt, though this should not be necessary; see possible reasons at: https://stackoverflow.com/questions/2508718/content-type-not-working-in-php
		header ('Content-Type: application/atom+xml; charset=utf-8');
		echo $xml;
		
		# Die to prevent any more output
		exit ();
	}
	
	
	/**
	 * RFC 3339 Date as required by Atom 1.0
	 *
	 * @link http://www.atomenabled.org/developers/syndication/
	 * @link http://www.faqs.org/rfcs/rfc3339.html
	 */
	private function rfc3339Date ($timestamp = 0 )
	{
		if (!$timestamp) {$timestamp = time ();}
		$date = date ('Y-m-d\TH:i:s', $timestamp);
		$matches = array ();
		if (preg_match ('/^([\-+])(\d{2})(\d{2})$/', date('O', $timestamp), $matches)) {
			$date .= $matches[1] . $matches[2] . ':' . $matches[3];
		} else {
			$date .= 'Z';
		}
		
		# Return the data
		return $date;
	}
}

?>
