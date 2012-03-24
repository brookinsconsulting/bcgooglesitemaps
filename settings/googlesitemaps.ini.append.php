<?php /*

[all2eGoogleSitemapSettings]
# Define Subtree to create Sitempa from
SitemapRootNodeID=2
# Define the XML file
Filename=sitemap
Filesuffix=.xml
# related to the eZ Publish root directory
Path=

[SiteAccessSettings]
# here you need to specify every siteaccess a sitemap shall be created for
# if no siteaccessarray is given, the default siteaccess will be used for generation
# SiteAccessArray[]
# SiteAccessArray[]=de
# SiteAccessArray[]=en

[Classes]
# include or exclude objects of classes listed in ClassFilterArray
ClassFilterType=exclude

# setting array to include/exclude classes in sitemap
ClassFilterArray[]
#ClassFilterArray[]=folder
#ClassFilterArray[]=article
#ClassFilterArray[]=image
#ClassFilterArray[]=forum
#ClassFilterArray[]=...

[NodeSettings]
# set false to include only main node of content object in sitemap
# set true to include all nodse of content object in sitemap
Main_Node_Only=false


*/ ?>
