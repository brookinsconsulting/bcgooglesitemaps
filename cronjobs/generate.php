<?php
/**
 * File containing the all2egooglesitemaps sitemap generator cronjob part
 *
 * @copyright Copyright (C) 2008 all2e GmbH. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package all2egooglesitemaps
 */

if ( !$isQuiet )
    $cli->output( "Generating Sitemap...\n"  );


// Get a reference to eZINI. append.php will be added automatically.
$ini = eZINI::instance( 'site.ini' );
$googlesitemapsINI = eZINI::instance( 'googlesitemaps.ini' );


// Settings variables
if ( $googlesitemapsINI->hasVariable( 'all2eGoogleSitemapSettings', 'SitemapRootNodeID' ) &&
     $googlesitemapsINI->hasVariable( 'all2eGoogleSitemapSettings', 'Path' ) &&
     $googlesitemapsINI->hasVariable( 'all2eGoogleSitemapSettings', 'Filename' ) &&
     $googlesitemapsINI->hasVariable( 'all2eGoogleSitemapSettings', 'Filesuffix' ) &&
     $googlesitemapsINI->hasVariable( 'Classes', 'ClassFilterType' ) &&
     $googlesitemapsINI->hasVariable( 'Classes', 'ClassFilterArray' ) &&
     $ini->hasVariable( 'SiteSettings','SiteURL' )
     )
{
    $sitemapRootNodeID = $googlesitemapsINI->variable( 'all2eGoogleSitemapSettings','SitemapRootNodeID' );

    $sitemapName = $googlesitemapsINI->variable( 'all2eGoogleSitemapSettings','Filename' );
    $sitemapSuffix = $googlesitemapsINI->variable( 'all2eGoogleSitemapSettings','Filesuffix' );
    $sitemapPath = $googlesitemapsINI->variable( 'all2eGoogleSitemapSettings','Path' );

    $classFilterType = $googlesitemapsINI->variable( 'Classes','ClassFilterType' );
    $classFilterArray = $googlesitemapsINI->variable( 'Classes','ClassFilterArray' );
}
else
{
    $cli->output( 'Missing INI Variables in configuration block GeneralSettings.' );
    return;
}

//getting custom set site access or default access
if ($googlesitemapsINI->hasVariable( 'SiteAccessSettings', 'SiteAccessArray' ))
{
    $siteAccessArray = $googlesitemapsINI->variable( 'SiteAccessSettings', 'SiteAccessArray' );
}
else
{
    $siteAccessArray = array($ini->variable( 'SiteSettings', 'DefaultAccess' ));
}

//fetching all language codes
$languages = array();

foreach($siteAccessArray as $siteAccess)
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

foreach ($languages as $language)
{
    if ( !$isQuiet )
        $cli->output( "Generating Sitemap for Siteaccess ".$language["siteaccess"]." \n" );
        
    $siteURL = $language['siteurl'];
    
    // Get the Sitemap's root node
    $rootNode = eZContentObjectTreeNode::fetch( $sitemapRootNodeID, $language['locale'] );

    if (!is_object($rootNode)) {
        $cli->output( "Invalid SitemapRootNodeID in configuration block GeneralSettings.\n" );
        return;
    }
    
    require_once "access.php";
    $access = changeAccess( array("name" => $language["siteaccess"],
                                  "type" => EZ_ACCESS_TYPE_URI
                                  ) );
    
    // Fetch the content tree
    $nodeArray = $rootNode->subTree( array(  'Language'         => $language['locale'],
                                             'ClassFilterType'  => $classFilterType,
                                             'ClassFilterArray' => $classFilterArray
                                           )
                                    );
    
    $xmlRoot = "urlset";
    $xmlNode = "url";

    // Define XML Child Nodes
    $xmlSubNodes = array("loc","lastmod","changefreq","priority");

    // Create the DOMnode
    $dom = new DOMDocument("1.0","UTF-8");

    // Create DOM-Root (urlset)
    $root = $dom->createElement($xmlRoot);
    $root->setAttribute( "xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9" );
    $root = $dom->appendChild($root);

    // Generate XML data
    foreach ($nodeArray as $subTreeNode)
    {
        // Values
        $urlAlias = 'http://'.$siteURL.'/'.$subTreeNode->attribute( 'url_alias' );
        
        $object = $subTreeNode->object();
        //$depth = $subTreeNode->attribute( 'depth' );
        $modified = date("c" , $object->attribute( 'modified' ));

        // Create new url element
        $node = $dom->createElement($xmlNode);
        // append to root node
        $node = $root->appendChild($node);

        // create new url subnode
        $subNode = $dom->createElement($xmlSubNodes[0]);
        $subNode = $node->appendChild($subNode);
        // set text node with data
        $date = $dom->createTextNode($urlAlias);
        $date = $subNode->appendChild($date);

        // create modified subnode
        $subNode = $dom->createElement($xmlSubNodes[1]);
        $subNode = $node->appendChild($subNode);
        // set data
        $lastmod = $dom->createTextNode($modified);
        $lastmod = $subNode->appendChild($lastmod);

    }
    // write XML Sitemap to file
    $xmlDataFile = $sitemapPath.$sitemapName.'_'.$language['siteaccess'].$sitemapSuffix;
    $dom->save($xmlDataFile);

    if ( !$isQuiet )
    {
        $cli->output( "Sitemap for site access ".$language['siteaccess']." (language code ".$language['locale'].") has been generated!\n\n" );
    }
}
?>