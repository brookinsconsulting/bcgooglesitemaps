<?php
/**
 * File containing the generatemultilingual.php cronjob
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2008 all2e GmbH. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package bcgooglesitemaps
 */

/**
 * File contains an eZ Publish cronjob part (script) to automatically
 * fetch all the content of the eZ Publish siteaccess database content
 * tree content nodes, transform the nodes fetched into an xml based
 * sitemap and writes the sitemap to disk.
 *
 * Sitemap is based on custom extension settings (array of siteaccess name strings),
 * this script iterate over each siteaccess building an array of site languages
 * (site locale and site url), then iterating over site language information fetch
 * the root node of the content tree (settings based) in each language and then all
 * child nodes in each language. Next iterating over an array of all nodes in all
 * locales, for each node, generate the sitemap xml representing that node.
 *
 * Finally a valid xml sitemap file is written out to disk (settings based var/ dir root by default)
 *
 * File containing the bcgooglesitemaps siteaccess sitemap generator cronjob part
 *
 */

/**
 * BC: In testing multi-lingual sites with single language siteaccess installations
 * we have found it is tipically best for the cronjob mode to be disabled. Otherwise
 * content in all possible languages is returned in subtree results.
 */
eZContentLanguage::clearCronjobMode();

/**
 * Get a reference to eZINI. append.php will be added automatically.
 */
$ini = eZINI::instance( 'site.ini' );
$bcgooglesitemapsINI = eZINI::instance( 'bcgooglesitemaps.ini' );

/**
 * BC: Testing for settings required by the script and defining other variables required by the script
 */
if ( $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'SitemapRootNodeID' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Path' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Filename' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Filesuffix' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Protocol' ) &&
     $bcgooglesitemapsINI->hasVariable( 'Classes', 'ClassFilterType' ) &&
     $bcgooglesitemapsINI->hasVariable( 'Classes', 'ClassFilterArray' ) &&
     $ini->hasVariable( 'SiteSettings', 'SiteURL' ) &&
     $ini->hasVariable( 'FileSettings', 'VarDir' )
     )
{
    /**
     * BC: Define root content tree node ID
     */
    $sitemapRootNodeID = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings', 'SitemapRootNodeID' );

    /**
     * BC: Define the sitemap basename and output file suffix
     */
    $sitemapName = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings', 'Filename' );
    $sitemapSuffix = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings', 'Filesuffix' );

    /**
     * BC: Define the sitemap base path, output file directory path. Path to directory to write out generated sitemaps
     */
    if( $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings', 'Path' ) != false )
    {
        $sitemapPath = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings', 'Path' );
    }
    else
    {
        $sitemapPath = $ini->variable( 'FileSettings', 'VarDir' );
    }

    /**
     * BC: Define the sitemap link protocol. Default http
     */
    $sitemapLinkProtocol = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings', 'Protocol' );

    /**
     * BC: Define content tree node fetch class filter. Array of class identifiers and whether to include or exclude them.
     */
    $classFilterType = $bcgooglesitemapsINI->variable( 'Classes', 'ClassFilterType' );
    $classFilterArray = $bcgooglesitemapsINI->variable( 'Classes', 'ClassFilterArray' );
}
else
{
    /**
     * BC: Alert user of missing ini settings variables
     */
    $cli->output( 'Missing INI Variables in configuration block GeneralSettings.' );
    return;
}

/**
 * BC: Fetch the array of siteaccess names (multi siteaccess; multi language)
 * which should be used to fetch content for the sitemap or the default
 * siteaccess name (one siteaccess; one language) when the custom settings are unavailable
 */
if( $bcgooglesitemapsINI->hasVariable( 'SiteAccessSettings', 'SiteAccessArray' ) )
{
    $siteAccessArray = $bcgooglesitemapsINI->variable( 'SiteAccessSettings', 'SiteAccessArray' );
}
else
{
    $siteAccessArray = array( $ini->variable( 'SiteSettings', 'DefaultAccess' ) );
}

/**
 * BC: Fetching all language codes
 */
$languages = array();

/**
 * BC: Iterate over each siteaccess and collect siteaccess local settings (site languages)
 */
foreach( $siteAccessArray as $siteAccess )
{
    $siteAccessINI = eZINI::instance( 'site.ini.append.php', 'settings/siteaccess/' . $siteAccess  );

    if ( $siteAccessINI->hasVariable( 'RegionalSettings', 'Locale' ) )
    {
        array_push( $languages, array( 'siteaccess' => $siteAccess,
                                       'locale' => $siteAccessINI->variable( 'RegionalSettings', 'Locale' ),
                                       'siteurl' => $siteAccessINI->variable( 'SiteSettings', 'SiteURL' ) ) );
    }
}

/**
 * BC: Preparing to fetch all content tree nodes by each language (Settings based locale)
 */
$nodeArray = array();

/**
 * BC: Iterate over each siteaccess locals
 */
foreach( $languages as $language )
{
    /**
     * BC: Alert user of the generation of the sitemap for the current language siteacces (name)
     */
    if ( !$isQuiet )
        $cli->output( "Generating sitemap content for siteaccess " . $language["siteaccess"] . " \n" );

    /**
     * BC: Fetch siteaccess site url
     */
    $siteURL = $language['siteurl'];

    /**
     * Get the Sitemap's root node
     */
    $rootNode = eZContentObjectTreeNode::fetch( $sitemapRootNodeID, $language['locale'] );

    /**
     * Test for content object fetch (above) failure to return a valid object.
     * Alert the user and terminate execution of script
     */
    if ( !is_object( $rootNode ) )
    {
        $cli->output( "Invalid SitemapRootNodeID in configuration block GeneralSettings; OR SitemapRootNodeID does not not have language translation for current siteaccess language.\n" );
        return;
    }

    /**
     * Change siteaccess
     */
    eZSiteAccess::change( array("name" => $language["siteaccess"], "type" => eZSiteAccess::TYPE_URI ) );

    /**
     * Fetch the content tree nodes (children) of the above root node (in a given locale)
     */
    $nodeArray[] = $rootNode->subTree( array( 'Language' => $language['locale'],
                                              'ClassFilterType' => $classFilterType,
                                              'ClassFilterArray' => $classFilterArray ) );

} // BC: End foreach( $languages as $language )

/**
 * Prepare new xml document
 */

$xmlRoot = "urlset";
$xmlNode = "url";

/**
 * Define XML Child Nodes
 */
$xmlSubNodes = array( 'loc', 'lastmod', 'changefreq', 'priority' );

/**
 * Create the DOMnode
 */
$dom = new DOMDocument( '1.0', 'UTF-8' );

/**
 * Create DOM-Root (urlset)
 */
$root = $dom->createElement( $xmlRoot );
$root->setAttribute( 'xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
$root = $dom->appendChild( $root );

/**
 * BC: Generate XML sitemap compatible data file contents
 * based on array of arrays containing content tree nodes in each language
 * for a given sitaccess or array of siteaccesses
 */
foreach( $nodeArray as $languageNodeArray )
{
    /**
     * BC: Iterate over language
     */
    foreach( $languageNodeArray as $subTreeNode )
    {
        /**
         * BC: Site node url alias (calculation)
         */
        $urlAlias = $sitemapLinkProtocol . $siteURL . '/' . $subTreeNode->attribute( 'url_alias' );

        /**
         * BC: Fetch node's object
         */
        $object = $subTreeNode->object();

        /**
         * $depth = $subTreeNode->attribute( 'depth' );
         */

        /**
         * BC: Fetch object's modified date
         */
        $modified = date( "c" , $object->attribute( 'modified' ) );

        /**
         * Create new url element
         */
        $node = $dom->createElement( $xmlNode );

        /**
         * Append to root node
         */
        $node = $root->appendChild( $node );

        /**
         * Create new page url subnode
         */
        $subNode = $dom->createElement( $xmlSubNodes[0] );
        $subNode = $node->appendChild( $subNode );

        /**
         * Set text node with data
         */
        $date = $dom->createTextNode( $urlAlias );
        $date = $subNode->appendChild( $date );

        /**
         * BC: Create 'modified' subnode and append child data to xml document being generated
         */
        $subNode = $dom->createElement( $xmlSubNodes[1] );
        $subNode = $node->appendChild( $subNode );

        /**
         * BC: Create 'lastmod' node and append child data to the xml document being generated
         */
        $lastmod = $dom->createTextNode( $modified );
        $lastmod = $subNode->appendChild( $lastmod );
    }
}

/**
 * BC: Build output xml data file name
 */
$xmlDataFile = $sitemapPath . '/' . $sitemapName . $sitemapSuffix;

/**
 * BC: Write sitemap xml file to disk
 */
$dom->save( $xmlDataFile );

/**
 * BC: Alert user of script completion
 */
if ( !$isQuiet )
{
    /**
     * @TODO: Extend message displayed to include more details of the content and context of the results written to disk
     */
    $cli->output( "Sitemap for site has been generated. See: $xmlDataFile\n\n" );
}

/**
 * Terminate execution and exit system normally
 */

?>