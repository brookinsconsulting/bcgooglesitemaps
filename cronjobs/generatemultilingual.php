<?php
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
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2008 all2e GmbH
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GNU GPL v2 (or later)
 * @version //autogentag//
 * @package bcgooglesitemaps
 */

/**
 * Alert user of script execution start
 */
if( !$isQuiet )
{
    $cli->output( "Generating Sitemap...\n"  );
}

/**
 * Get a reference to eZINI. append.php will be added automatically.
 */
$ini = eZINI::instance( 'site.ini' );
$bcgooglesitemapsINI = eZINI::instance( 'bcgooglesitemaps.ini' );

/**
 * BC: Testing for settings required by the script and defining other variables required by the script
 */
if( $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'SitemapRootNodeID' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Path' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Filename' ) &&
     $bcgooglesitemapsINI->hasVariable( 'BCGoogleSitemapSettings', 'Filesuffix' ) &&
     $bcgooglesitemapsINI->hasVariable( 'Classes', 'ClassFilterType' ) &&
     $bcgooglesitemapsINI->hasVariable( 'Classes', 'ClassFilterArray' ) &&
     $ini->hasVariable( 'SiteSettings','SiteURL' )
     )
{
    /**
     * BC: Define root content tree node ID
     */
    $sitemapRootNodeID = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings','SitemapRootNodeID' );

    /**
     * BC: Define the sitemap basename, output file suffix and path to directory to write out generated sitemaps
     */
    $sitemapName = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings','Filename' );
    $sitemapSuffix = $bcgooglesitemapsINI->variable( 'BCGoogleSitemapSettings','Filesuffix' );
    $sitemapPath = $ini->variable( 'FileSettings','VarDir' );

    /**
     * BC: Define content tree node fetch class filter. Array of class identifiers and whether to include or exclude them.
     */
    $classFilterType = $bcgooglesitemapsINI->variable( 'Classes','ClassFilterType' );
    $classFilterArray = $bcgooglesitemapsINI->variable( 'Classes','ClassFilterArray' );
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
    $siteAccessArray = array($ini->variable( 'SiteSettings', 'DefaultAccess' ));
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
    $specificINI = eZINI::instance( 'site.ini.append.php', 'settings/siteaccess/'.$siteAccess  );
    if ($specificINI->hasVariable( 'RegionalSettings', 'Locale' ))
    {
        array_push($languages, array('siteaccess' => $siteAccess,
                                     'locale'     => $specificINI->variable( 'RegionalSettings', 'Locale' ),
                                     'siteurl'    => $specificINI->variable( 'SiteSettings','SiteURL' )
                                    )
                  );
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
        $cli->output( "Generating Sitemap for Siteaccess ".$language["siteaccess"]." \n" );

    $siteURL = $language['siteurl'];

    /**
     * Get the Sitemap's root node
     */
    $rootNode = eZContentObjectTreeNode::fetch( $sitemapRootNodeID, $language['locale'] );

    /**
     * Test for content object fetch (above) failure to return a valid object.
     * Alert the user and terminate execution of script
     */
    if (!is_object($rootNode)) {
        $cli->output( "Invalid SitemapRootNodeID in configuration block GeneralSettings.\n" );
        return;
    }

    /**
     * Prepare to create new xml document
     */
    require_once 'extension/bcgooglesitemaps/lib/access.php';
    $access = changeAccess( array( 'name' => $language['siteaccess'],
                                   'type' => EZ_ACCESS_TYPE_URI
                                  ) );

    /**
     * Fetch the content tree nodes (children) of the above root node (in a given locale)
     */
    $nodeArray[] = $rootNode->subTree( array( 'Language' => $language['locale'],
                                              'ClassFilterType' => $classFilterType,
                                              'ClassFilterArray' => $classFilterArray
                                            )
                                     );

} // BC: End foreach($languages as $language)

/**
 * Prepare to create new xml document
 */
$xmlRoot = "urlset";
$xmlNode = "url";

/**
 * Define XML Child Nodes
 */
$xmlSubNodes = array('loc','lastmod','changefreq','priority');

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
        $urlAlias = 'http://'.$siteURL.'/'.$subTreeNode->attribute( 'url_alias' );

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
        $modified = date("c" , $object->attribute( 'modified' ));

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
        // create modified subnode
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
 * BC: Disable language in output filename (For a limited use case requirement)
 */
/*
$xmlDataFile = $sitemapPath.$sitemapName.'_' . $language['siteaccess'] . $sitemapSuffix;
*/
$xmlDataFile = $sitemapPath . $sitemapName . '_' . $sitemapSuffix;


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
     * BC: Disable language in output filename (For a limited use case requirement)
     */
    /*
         $cli->output( "Sitemap for siteaccess ".$language['siteaccess']." (language code ".$language['locale'].") ." has been generated!\n\n" );
    */

    /**
     * BC: Slightly different user alert at end of program
     * @TODO: Extend message displayed to include more details of the content and context of the results written to disk
     */
    $cli->output( "Sitemap for site has been generated!\n\n" );
}

// Terminate execution and exit system normally

?>