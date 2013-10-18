=== Plugin Name ===
Contributors: WP Widget Cache
Donate link: https://github.com/rooseve/wp-widget-cache
Tags: widget, sidebar, cache, caching, performance, google, wp-cache, wp-super-cache
Requires at least: 2.5.3
Tested up to: 3.6.1
Stable tag: 0.26

Cache the output of your blog widgets. Usually it will significantly reduce the SQL queries to your database and speed up your site.

== Description ==

A **high-performance** caching plugin for WordPress, and a **plus** for WP-Cache or WP Super Cache!

**Why we need this WP Widget Cache?**

WP Widget Cache can cache the output of your blog widgets. Usually it will significantly reduce the SQL queries to your database and speed up your site.

I think you’ve heard of [WP-Cache](http://wordpress.org/extend/plugins/wp-cache/) or [WP Super Cache](http://wordpress.org/extend/plugins/wp-super-cache/), they are both top plugins for WordPress, which make your site much faster and responsive. Here is how cache works:

"caching WordPress pages and storing them in a static file for serving future requests directly from the file rather than loading and compiling the whole PHP code and the building the page from the database".

If your site get a very high traffic, or your blog are hosted on a shared server, or Google crawl your site frequently, you do need cache. If you use widgets, you do need WP Widget Cache.

**Why WP-Cache or WP Super Cache is not enough?**

WP-Cache or WP Super Cache cache ‘pages’, and WP Widget Cache cache ‘widgets’ or your sidebar, that’s the difference.

Let me explain this more clearly:

If some of your page is very popular, and people keep visit this page, then the page cache will be very helpful. But what if the user click some link and visit another page of your blog, or **Google is crawling your site**? Cache another whole page? Actually that’s not necessary for most time. As we all know, WordPress share the same widgets, they’re all the same, maybe on all the pages of your site. For example, the Categories widget, this maybe never change, the Archives widget, maybe changes once a month. So it’s really really not that necessary to query the database again， especially when you use a lot of widgets.

WP Widget Cache is not to replace the WP-Cache or WP Super Cache, it’s a plus for them, as it reducing the cost for caching a new page. you can set the cache time for each widget individually, seconds to years, whatever you like. For Categories widget, days maybe fine, for Recent Comments widget, seconds maybe fine.

**How effective it is?**

That depends on how many and what widgets you use, some sites can gain more than **70%** improvement.

**Notice**

There're some widgets that **should not be cached**!!

For more information, please visit the [Other Notes](http://wordpress.org/extend/plugins/wp-widget-cache/other_notes/).

***Thanks***

*I want to say thanks to Alan Trewartha (the author of [Widget Logic](http://wordpress.org/extend/plugins/widget-logic/) plugin) and Dragan Bosnjak (the author of Cache Class), their codes are very helpful for me to finish this plugin.*

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. For 0.25+, make sure the folder WP_CONTENT_DIR, normally '/wp-content/', is dir createable and writable, as the widget cache folder will be WP_CONTENT_DIR.'/widget-cache'
1. Configure the plugin through Admin -> Design -> Widgets(see the Screenshots)

== Frequently Asked Questions ==

= Work with WP-Cache or WP Super Cache? =

Yep, WP Widget Cache is a plus for them, as it reducing the cost for caching a new page

= Want to be sure it really reduce your server usage? =

View the source of your site page, there maybe some code like this at the foot:

	<!-- ** queries. ** seconds. -->

If not, please put these codes in your footer template:

	<!-- <?php echo get_num_queries(); ?> queries. <?php timer_stop(1); ?> seconds. -->

After using the WP Widget Cache, I think you'll find the number of 'queries' reducing a lot. Of course, that will depend on how many and what widgets you use, some sites can gain more than **70%** improvement.

= How to know it works? =

You can have a look at the source of the web page, and search 
	
	<!--WP Widget Cache End -->


== Screenshots ==

1. The WP Widget Cache Options
2. WP Widget Cache Settings

== Changelog ==

= 0.26.0 =
* Optimize: refactor code

= 0.25.5 =
* Bug Fix: fix setting issues on wp 3.4

= 0.25.4 =
* Enhancement: create a folder for each domain, or $_SERVER['HTTP_HOST'], so if you host many domains under one wp system, no problem.

= 0.25.3 =
* Bug Fix: use WP_CONTENT_DIR instead of ABSPATH.wp-content, as people might have their own custom folders

= 0.25.2 =
* Bug Fix: not cache options any more (it's already cached by wp itself).

= 0.25.1 =
* Enhancement: better notice when cache folder can't be created in WP_CONTENT_DIR

= 0.25 =
* Enhancement: better cachefile management, now each wiget as a cache group, as you may have many cache versions for different users

= 0.22.1 =
* Bug Fix: fix some cache clear issues.

= 0.22 =
* Enhancement: add some delete funcs, so you can clear each widget cache individually

= 0.21 =
* Enhancement: user agent now a vary param, if you have different themes for different browsers, like mobile site, it won't be a problem now

= 0.2 =
* Enhancement: add setting page, better control with this plugin

= 0.15 =
* Bug Fix: auto expire options not save.

= 0.15 =
* Enhancement: add auto expire options, like when you post, comment, etc.

= 0.1 =
* Initial release.

== Notice ==

There're some widgets that should not be cached!!

Some widgets are **dynamic**, that means they show different content in different conditions, for example, for different pages, for login / unlogin users.

If you use such dynamic widgets, don't worry, just left the cache expire time(see the screenshots) field **empty** or **0**, which will prevent the WP Widget Cache to do anything.

Here's a list of such widgets to be finished, if you know something new, just visit [here](https://github.com/rooseve/wp-widget-cache/issues), and leave a comment.

* [Widget Logic](http://wordpress.org/extend/plugins/widget-logic/)
* [MiniMeta Widget](http://wordpress.org/extend/plugins/minimeta-widget/)
