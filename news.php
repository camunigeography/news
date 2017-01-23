<?php


# Class to create a news management system

#!# Add support for PDF conversion so it can be treated as an image


require_once ('frontControllerApplication.php');
class news extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'database' => 'news',
			'table' => 'articles',
			'administrators' => true,
			'imageDirectory' => NULL,
			'imageLocation' => NULL,	// Equivalent to imageDirectory in URL terms
			'thumbnailsSubfolder' => 'thumbnails/',
			'userCallback' => NULL,		// Callback function
			'divId' => 'newsarticles',
			'h1' => '<h1>News submission</h1>',
			'imageWidthMain' => 300,
			'imageWidthThumbnail' => 150,
			'headingLevelPortal' => 3,	// Heading level (e.g. 3 for h3) for the news titles
			'headingLevelListing' => 2,	// Heading level (e.g. 2 for h2) for the news titles
			'newsPermalinkUrl' => '/news/',
			'feedPermalinkUrl' => '/news/feed.rss',
			'archivePermalinkUrl' => '/news/previous.html',
			'authentication' => false,	// Defined on a per-action basis below
			'useEditing' => true,
			'recent' => 10,
			'internalHostRegexp' => NULL,
			'rssTitle' => NULL,
			'rssImage' => NULL,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Define the available formats and their properties
	private $exportFormats = array (
		'frontpage'	=> array (
			'extension' => 'html',
		),
		'recent'	=> array (
			'extension' => 'html',
		),
		'archive'	=> array (
			'extension' => 'html',
		),
		'json'		=> array (
			'extension' => 'json',
		),
		'feed'		=> array (
			'extension' => 'rss',
		),
	);
	
	
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
			'editing' => array (
				'description' => false,
				'url' => 'articles/',
				'tab' => 'Data editing',
				'icon' => 'pencil',
				'administrator' => true,
			),
			'view' => array (
				'description' => false,
				'url' => 'view/',
				'tab' => 'View articles',
				'icon' => 'application_view_list',
				'administrator' => true,
				'authentication' => true,
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
			  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  PRIMARY KEY (`username`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='System administrators';
			
			-- Articles
			CREATE TABLE IF NOT EXISTS `articles` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key' PRIMARY KEY,
			  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Title of article',
			  `sites` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT 'Site(s), comma-separated',
			  `photograph` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Image (if available)',
			  `imageCredit` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Image credit (if any)',
			  `richtext` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Article text (max 2 paragraphs), including mention of relevant person',
			  `richtextAbbreviated` text COLLATE utf8_unicode_ci COMMENT 'Abbreviated article text',
			  `urlInternal` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Webpage on our site, if any',
			  `urlExternal` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'External webpage giving more info, if any',
			  `startDate` date NOT NULL COMMENT 'Date to appear on website',
			  `moniker` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Unique text key (a-z,0-9) (acts as approval field also)',
			  `frontPageOrder` enum('1','2','3','4','5','6','7','8','9','10') COLLATE utf8_unicode_ci DEFAULT NULL COMMENT 'Ordering for visibility (1 = highest on page)',
			  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Submission date',
			  UNIQUE KEY `moniker` (`moniker`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			
			-- Settings
			CREATE TABLE `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Automatic key (ignored)' PRIMARY KEY,
			  `sites` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Sites available, one per line, as moniker,label'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Settings';
			INSERT INTO `settings` (`id`, `sites`) VALUES (1, 'example,Example');
		";
	}
	
	
	# Additional initialisation, pre-actions
	public function mainPreActions ()
	{
		# Process the sites setting, which is saved as a textarea block
		if ($this->action != 'settings') {
			$sitesSetting = array ();
			$lines = explode ("\n", str_replace ("\r\n", "\n", trim ($this->settings['sites'])));
			foreach ($lines as $line) {
				list ($moniker, $label) = explode (',', $line, 2);
				$sitesSetting[$moniker] = $label;
			}
			$this->settings['sites'] = $sitesSetting;
		}
		
	}
	
	
	# Additional initialisation
	public function main ()
	{
		# Load required libraries
		require_once ('image.php');
		
		# Get the user details
		if (!$this->userDetails = $this->userDetails ()) {
			$requiresAuth = (isSet ($this->actions[$this->action]['authentication']) && $this->actions[$this->action]['authentication']);
			if ($requiresAuth) {	// Use authentication check for authorisation
				echo "\n<p>You do not seem to be a registered user. Please <a href=\"{$this->baseUrl}/feedback.html\">contact the Webmaster</a> if this is incorrect.</p>";
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
		echo "\n\n" . "<p>Welcome, {$this->userDetails['forename']}, to the news submission system.</p>";
		
		# Show the reporting screen
		echo "\n<h2>Submit an item of news</h2>";
		echo $this->submissionForm ();
	}
	
	
	# Submit page
	public function submit ()
	{
		# Show the report form
		echo $this->submissionForm ();
	}
	
	
	# Helper function to define the dataBinding attributes
	private function formDataBindingAttributes ()
	{
		# Define the attributes
		$attributes = array (
			'photograph' => array ('directory' => $this->photographDirectoryOriginals, 'forcedFileName' => $this->user, 'allowedExtensions' => array ('jpg'), 'lowercaseExtension' => true, 'thumbnail' => true, ),
			#!# Ideally there would be some way to define a set of domain names that are treated as 'internal' so that http://www.example.org/foo/ could be entered rather than /foo/ to avoid external links being created
			'richtext' => array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 300, ),
			'richtextAbbreviated' => array ('editorToolbarSet' => 'BasicLonger', 'width' => 600, 'height' => 180, ),
			'sites' => array ('type' => 'checkboxes', 'values' => $this->settings['sites'], 'separator' => ',', 'defaultPresplit' => true, 'output' => array ('processing' => 'special-setdatatype'), ),
			'startDate' => array ('default' => 'timestamp', 'picker' => true, ),
			'urlInternal' => array ('placeholder' => 'http://', 'regexp' => '^https?://'),
			'frontPageOrder' => array ('nullText' => false, ),
			'moniker' => array ('regexp' => '^([a-z0-9]+)$'),
		);
		
		# Return the attributes
		return $attributes;
	}
	
	
	# Submission form
	private function submissionForm ()
	{
		# Determine fields to exclude
		$exclude = array ();
		if (!$this->userIsAdministrator ()) {
			$exclude = array ('moniker', 'richtextAbbreviated', 'frontPageOrder');
		}
		
		# Create the form
		$form = new form (array (
			'displayDescriptions' => false,
			'databaseConnection' => $this->databaseConnection,
			'size' => 70,
			'formCompleteText' => 'Thanks for submitting this article. The Webmaster will review it and confirm when it is online.',
		));
		
		# Databind the form
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'intelligence' => true,
			'exclude' => $exclude,
			'attributes' => $this->formDataBindingAttributes (),
		));
		$form->email (array (
			'name' => 'email',
			'title' => 'Your e-mail address (purely for acknowledgement)',
			'default' => $this->userDetails['email'],
			'editable' => false,
		));
		
		# Set to mail the admin
		$form->setOutputEmail ($this->settings['administratorEmail'], $this->settings['administratorEmail'], 'New news submission', NULL, 'email');
		
		# Obtain the result
		if (!$result = $form->process ()) {return false;}
		
		# Remove fixed data
		unset ($result['email']);
		
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
		
		
	}
	
	
	# View all approved articles
	public function view ()
	{
		# Format as a table
		$html = $this->exportArchive ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to provide a list of export formats
	public function export ()
	{
		# Start the HTML
		$html = '';
		
		# Create the list
		foreach ($this->settings['sites'] as $site => $label) {
			
			# Create the table entries
			$table = array ();
			foreach ($this->exportFormats as $format => $attributes) {
				$title = "<strong>" . ucfirst ($format) . '</strong> format';
				$location = "{$this->baseUrl}/export/{$format}.{$attributes['extension']}?site={$site}";
				$phpCode = "<a href=\"{$location}\">{$_SERVER['_SITE_URL']}{$location}</a>";
				$table[$title] = $phpCode;
			}
			
			# Compile the HTML
			$html .= "\n<h3>" . htmlspecialchars ($label) . ':</h3>';
			$html .= application::htmlTableKeyed ($table, array (), true, 'lines', $allowHtml = true);
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
		
		# Determine the site
		$site = (isSet ($_GET['site']) && strlen ($_GET['site']) && array_key_exists ($_GET['site'], $this->settings['sites']) ? $_GET['site'] : false);
		
		# If $_GET['REMOTE_ADDR'] is supplied as a query string argument, proxy that through
		$remoteAddr = $_SERVER['REMOTE_ADDR'];
		if (isSet ($_GET['REMOTE_ADDR'])) {
			$remoteAddr = $_GET['REMOTE_ADDR'];
		}
		
		# Add a link to adding an article
		$delimiter = '@';
		$isInternal = preg_match ($delimiter . addcslashes ($this->settings['internalHostRegexp'], $delimiter) . $delimiter, gethostbyaddr ($remoteAddr));
		if ($isInternal) {
			$html .= "\n<p id=\"submitlink\" class=\"actions\"><a href=\"{$this->baseUrl}/\"><img src=\"/images/icons/add.png\" class=\"icon\" /> Submit news</a></p>";
		}
		
		# Add RSS link if the output type is HTML
		if ($this->exportFormats[$format]['extension'] == 'html') {
			$html .= "\n<p class=\"right\"><a href=\"{$this->settings['feedPermalinkUrl']}\"><img src=\"/images/icons/feed.png\" alt=\"RSS icon\" title=\"RSS feed\" class=\"icon\" /></a></p>";
		}
		
		# Construct the HTML based on the selected format
		$function = 'export' . ucfirst ($format);
		$html .= $this->{$function} ($site);
		
		# Surround with a div (frontControllerApplication will have stripped the 'div' setting when the export flag is on)
		if ($this->settings['divId']) {
			$html = "\n<div id=\"{$this->settings['divId']}\">\n" . $html . "\n\n</div><!-- /#{$this->settings['divId']} -->";
		}
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to format the articles as an HTML table
	public function exportFrontpage ($site)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles or end
		#!# This needs to be ordered by date,ordering
		if (!$articles = $this->getArticles ($site, false, 'frontPageOrder')) {
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
	public function exportRecent ($site)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles or end
		if (!$articles = $this->getArticles ($site, $this->settings['recent'])) {
			return "\n<p>There are no items of news at present.</p>";
		}
		
		# Build the HTML
		$html  = '';
		foreach ($articles as $id => $article) {
			$html .= $this->articleTitle ($article, true);
			$html .= $this->articleImage ($article, true);
			$html .= $this->articleBody ($article, true);
		}
		
		# Add a link to remainder
		$totalArticles = $this->getTotalArticles ();
		if ($totalArticles > $this->settings['recent']) {
			$html .= "\n<hr id=\"browseearlier\" />";
			#!# Ideally this would link to the next in the list
			$html .= "\n<p><a href=\"{$this->settings['archivePermalinkUrl']}\">Browse earlier articles&hellip;</a></p>";
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to format the table as a listing
	public function exportArchive ($site)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles or end
		if (!$articles = $this->getArticles ($site)) {
			return "\n<p>There are no items of news.</p>";
		}
		
		# Build the HTML
		$html  = '';
		foreach ($articles as $id => $article) {
			$html .= $this->articleTitle ($article, true);
			$html .= $this->articleImage ($article, true);
			$html .= $this->articleBody ($article, true);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to get the articles
	private function getArticles ($site, $limit = false, $requireField = false)
	{
		# Define prepared statement values
		$preparedStatementValues = array ();
		$preparedStatementValues['site'] = '%' . $site . '%';
		
		# Get the data
		$query = "SELECT
			*,
			CONCAT('{$this->settings['archivePermalinkUrl']}','#',moniker) AS articlePermalink,
			DATE_FORMAT(startDate, '%D %M, %Y') AS date
			FROM {$this->dataSource}
			WHERE
				    moniker != '' AND moniker IS NOT NULL"
				. ($requireField ? " AND {$requireField} IS NOT NULL" : '')
				. " AND sites LIKE :site
			ORDER BY "
				. ($requireField ? $requireField . ' ASC, ' : '')
				. "startDate DESC, timestamp DESC "
			. ($limit ? "LIMIT {$limit} " : '') .
		';';
		$articles = $this->databaseConnection->getData ($query, $this->dataSource, true, $preparedStatementValues);
		
		# If there is an internal URL, chopping the server name part if on the same site
		foreach ($articles as $key => $article) {
			if ($article['urlInternal']) {
				$delimiter = '@';
				$articles[$key]['urlInternal'] = preg_replace ($delimiter . '^' . addcslashes ($_SERVER['_SITE_URL'], $delimiter) . $delimiter, '', $article['urlInternal']);
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
			$html  = "\n" . $article['richtext'];
		} else {
			$html  = "\n" . ($article['richtextAbbreviated'] ? $article['richtextAbbreviated'] : $article['richtext']);
			if ($article['richtextAbbreviated']) {
				$html .= "\n<p><a href=\"{$article['articlePermalink']}\">Read more &hellip;</a></p>";
			}
		}
		
		# In listing mode, add a read more link, favouring internal over external
		if ($listingMode) {
			if ($article['urlInternal'] || $article['urlExternal']) {
				$readMoreLink = ($article['urlInternal'] ? $article['urlInternal'] : $article['urlExternal']);
				$target = (!$article['urlInternal'] ? ' target="_blank"' : '');	// Add if not internal
				$html .= "\n<p><a href=\"{$readMoreLink}\"" . $target . ">Read more &hellip;</a></p>";
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to compile the article image tag
	public function articleImage ($article, $alignright = false)
	{
		# End if none
		if (!$article['photograph']) {return false;}
		
		# End if not readable
		$imageFilename = $article['id'] . '.jpg';
		if (!is_readable ($this->photographDirectoryMain . $this->settings['thumbnailsSubfolder'] . $imageFilename)) {return false;}
		
		# Assemble the location in URL terms
		$location  = $this->settings['imageLocation'] . $this->settings['thumbnailsSubfolder'] . $imageFilename;
		
		# Compile the image
		$description = htmlspecialchars ($article['title']);
		$html = "<a href=\"{$article['primaryUrl']}\"><img src=\"{$location}\" alt=\"{$description}\" border=\"0\"" . ($alignright ? ' align="right" class="shiftup"' : '') . ' /></a>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine the primary link (used for the image and the heading)
	private function primaryUrl ($article)
	{
		# If there is an internal URL, use that
		if ($article['urlInternal']) {
			return $article['urlInternal'];
		}
		
		# If there is an external URL, use that
		if ($article['urlExternal']) {
			return $article['urlExternal'];
		}
		
		# Otherwise, return the article permalink (basically an anchor in the all-articles mode
		return $article['articlePermalink'];
	}
	
	
	# Admin editing section, substantially delegated to the sinenomine editing component
	public function editing ($attributes = array (), $deny = false)
	{
		# Get the databinding attributes
		$dataBindingAttributes = $this->formDataBindingAttributes ();
		
		# Delegate to the standard function for editing
		echo $this->editingTable ($this->settings['table'], $dataBindingAttributes, 'ultimateform');
	}
	
	
	# JSON output
	private function exportJson ($site, $maximumEntries = 5)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles
		$articles = $this->getArticles ($site, false, 'frontPageOrder');
		
		# Decorate
		foreach ($articles as $id => $article) {
			$articles[$id]['imageHtml'] = $this->articleImage ($article, false);
			$articles[$id]['articleHtml'] = ($article['richtextAbbreviated'] ? $article['richtextAbbreviated'] : $article['richtext']);
		}
		
		# Limit if required
		if (isSet ($_GET['limit']) && ctype_digit ($_GET['limit'])) {
			$limit = $_GET['limit'];
			$i = 1;
			foreach ($articles as $id => $article) {
				if ($i > $limit) {unset ($articles[$id]);}
				$i++;
			}
		}
		
		# Send the feed
		header ('Content-type: application/json');
		echo json_encode ($articles, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		
		# Die to prevent any more output
		exit ();
	}
	
	
	# RSS (Atom) news feed
	private function exportFeed ($site, $maximumEntries = 24)
	{
		# End if no/invalid site
		if (!$site) {return false;}
		
		# Get the articles
		$articles = $this->getArticles ($site, $this->settings['recent']);
		
		# Define the base page
		$fullBaseUrl = "{$_SERVER['_SITE_URL']}{$this->baseUrl}";
		
		# Build the XML
		$xml  = '<' . '?' . 'xml version="1.0" encoding="utf-8"?>';	// Use this syntax to avoid confusing the editor
		$xml .= "\n<feed xmlns=\"http://www.w3.org/2005/Atom\">";
		$xml .= "\n\t<title>" . htmlspecialchars ($this->settings['rssTitle']) . "</title>";
		$xml .= "\n\t<icon>{$this->settings['rssImage']}</icon>";
		$xml .= "\n\t<link rel=\"self\" href=\"{$_SERVER['_SITE_URL']}{$this->settings['feedPermalinkUrl']}\"/>";
		$xml .= "\n\t<updated>" . $this->rfc3339Date () . "</updated>";
		$xml .= "\n\t<id>{$_SERVER['_SITE_URL']}{$this->settings['newsPermalinkUrl']}</id>";
		$xml .= "\n\t<author>\n\t\t<name>{$_SERVER['_SITE_URL']}{$this->settings['newsPermalinkUrl']}</name>\n\t</author>\n";
		
		# Add each entry
		foreach ($articles as $article) {
			$articleText = ($article['richtextAbbreviated'] ? $article['richtextAbbreviated'] : $article['richtext']);
			$xml .= "\n\t<entry>";
			$xml .= "\n\t\t<title>" . htmlspecialchars ($article['title']) . "</title>";
			$xml .= "\n\t\t<link href=\"{$_SERVER['_SITE_URL']}{$article['articlePermalink']}\"/>";
			$xml .= "\n\t\t<id>{$_SERVER['_SITE_URL']}{$article['articlePermalink']}</id>";
			$xml .= "\n\t\t<updated>" . $this->rfc3339Date (strtotime ($article['startDate'])) . '</updated>';
			$xml .= "\n\t\t<summary>" . str_replace ("\n", ' ', trim (htmlspecialchars (strip_tags ($articleText)))) . "</summary>";
			$xml .= "\n\t</entry>\n";
		}
		
		# Close the feed
		$xml .= "\n</feed>";
		
		# Send the feed
		header ('Content-type: application/atom+xml');
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