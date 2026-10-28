# Generates block.json files for Techvenza Core dynamic blocks.
import json, os, sys
OUT = '/home/claude/TECH/wp-content/plugins/techvenza-core/blocks'
S = lambda d='': {'type':'string','default':d}
B = lambda d=False: {'type':'boolean','default':d}
N = lambda d=0: {'type':'number','default':d}
A = lambda d=None: {'type':'array','default':d if d is not None else []}
SURF = {'surface': S('default'), 'spacing': S('default')}
SURF_C = [
 {'key':'surface','type':'select','label':'Background','panel':'Layout','options':[{'value':'default','label':'Page background'},{'value':'alt','label':'Surface (white)'}]},
 {'key':'spacing','type':'select','label':'Vertical spacing','panel':'Layout','options':[{'value':'default','label':'Default'},{'value':'tight','label':'Tight'},{'value':'none-top','label':'No top padding'},{'value':'none-bottom','label':'No bottom padding'}]},
]
def HEAD(eyebrow='', heading='', desc='', link='', url=''):
    a = {'eyebrow':S(eyebrow),'heading':S(heading),'description':S(desc),'linkLabel':S(link),'linkUrl':S(url)}
    c = [{'key':'eyebrow','type':'text','label':'Eyebrow','panel':'Heading'},{'key':'heading','type':'text','label':'Heading','panel':'Heading'},
         {'key':'description','type':'textarea','label':'Description','panel':'Heading'},{'key':'linkLabel','type':'text','label':'Link label','panel':'Heading'},{'key':'linkUrl','type':'url','label':'Link URL','panel':'Heading'}]
    return a, c
PERIODS = [{'value':'24h','label':'Last 24 hours'},{'value':'7d','label':'Last 7 days'},{'value':'30d','label':'Last 30 days'},{'value':'all','label':'All time'}]
TYPES = [{'value':'deep-dive','label':'Deep dive'},{'value':'analysis','label':'Analysis'},{'value':'guide','label':'Guide'},{'value':'opinion','label':'Perspective'},{'value':'news','label':'News'}]
blocks = {}
def blk(name, title, desc, attrs, controls, icon='layout', extra=None, surface=True, script=None, style=None):
    a = dict(attrs); c = list(controls)
    if surface:
        a.update(SURF); c += SURF_C
    j = {'$schema':'https://schemas.wp.org/trunk/block.json','apiVersion':3,'name':'techvenza/'+name,'title':title,'category':'techvenza',
         'icon':icon,'description':desc,'textdomain':'techvenza-core','attributes':a,
         'supports':{'html':False,'anchor':True,'className':True,'customClassName':True,'reusable':True},
         'editorScript':'techvenza-blocks-editor','render':'file:./render.php','tvControls':c}
    if script: j['viewScript'] = script
    if style: j['style'] = style
    if extra: j.update(extra)
    blocks[name] = j

# ---------- Site chrome ----------
blk('logo','Techvenza logo','Site logo: uploaded logo from Settings → Brand, or the Techvenza wordmark.',
    {'variant':S('auto')},[{'key':'variant','type':'select','label':'Colour','options':[{'value':'auto','label':'Automatic'},{'value':'light','label':'For light backgrounds'},{'value':'dark','label':'For dark backgrounds'}]}],'admin-site',surface=False)
blk('nav-menu','Menu','Renders a menu location managed in Appearance → Menus. Nested items become dropdowns; an item with CSS class tv-mega-categories becomes the categories mega-menu.',
    {'location':S('primary'),'variant':S('header'),'showTitle':B(False),'title':S('')},
    [{'key':'location','type':'select','label':'Menu location','options':[{'value':v,'label':l} for v,l in [('primary','Header — primary'),('mobile','Mobile drawer'),('footer-1','Footer column 1'),('footer-2','Footer column 2'),('footer-3','Footer column 3'),('footer-4','Footer column 4'),('legal','Legal links')]]},
     {'key':'variant','type':'select','label':'Style','options':[{'value':'header','label':'Header navigation'},{'value':'footer','label':'Footer column'},{'value':'inline','label':'Inline links'}]},
     {'key':'showTitle','type':'toggle','label':'Show menu name as heading'},{'key':'title','type':'text','label':'Heading override'}],'menu',surface=False,
    extra={'tvHint':'Edit the links in Appearance → Menus. Assign the menu to the chosen location.'})
blk('header-actions','Header actions','Search button, account menu (guest vs signed-in, role-aware), Become an Author button and mobile menu toggle.',
    {'showSearch':B(True),'showAccount':B(True),'ctaLabel':S(''),'ctaUrl':S('')},
    [{'key':'showSearch','type':'toggle','label':'Search button'},{'key':'showAccount','type':'toggle','label':'Account menu'},{'key':'ctaLabel','type':'text','label':'Button label','help':'Empty = Settings → Header & Footer.'},{'key':'ctaUrl','type':'url','label':'Button link'}],'admin-users',surface=False)
blk('search-overlay','Search overlay','Full-screen search with live suggestions (opened by the header search button or the / key).',{}, [],'search',surface=False,extra={'tvPreview':'placeholder','tvHint':'Hidden until opened. Suggestions come from the REST search endpoint.'})
blk('mobile-drawer','Mobile menu drawer','Slide-in navigation for small screens.',{'location':S('mobile')},[{'key':'location','type':'select','label':'Menu location','options':[{'value':'mobile','label':'Mobile drawer'},{'value':'primary','label':'Header — primary'}]}],'menu-alt',surface=False,extra={'tvPreview':'placeholder','tvHint':'Hidden until the menu button is pressed.'})
blk('footer-about','Footer about','Logo, about text and social links (Settings → Header & Footer).',{'text':S('')},[{'key':'text','type':'textarea','label':'About text','help':'Empty = Settings → Header & Footer.'}],'info',surface=False)
blk('footer-bottom','Footer bottom bar','Copyright with dynamic year, legal links menu and cookie preferences link.',{'text':S('')},[{'key':'text','type':'text','label':'Copyright text','help':'Merge tags {year} {site} {company}. Empty = Settings.'}],'editor-insertmore',surface=False)
blk('social-links','Social links','Icons for the social profiles saved in Settings → Header & Footer.',{},[],'share',surface=False)

# ---------- Home ----------
blk('announcement','Announcement strip','Compact editorial highlight strip. Uses Settings → Header & Footer by default, with optional scheduling.',
    {'useSettings':B(True),'pill':S('New'),'text':S(''),'linkLabel':S(''),'linkUrl':S('')},
    [{'key':'useSettings','type':'toggle','label':'Use Settings → Header & Footer','help':'Switch off to set the text here.'},{'key':'pill','type':'text','label':'Label','showIf':{'useSettings':False}},{'key':'text','type':'text','label':'Text','showIf':{'useSettings':False}},{'key':'linkLabel','type':'text','label':'Link label','showIf':{'useSettings':False}},{'key':'linkUrl','type':'url','label':'Link URL','showIf':{'useSettings':False}}],'megaphone',surface=False)
blk('hero','Hero','Homepage hero: display headline with gradient words, introduction, two buttons, stats and topic chips.',
    {'eyebrow':S('Independent technology journalism'),'heading':S('Technology, explained by the people'),'gradientText':S('who build it.'),
     'lead':S('Clear, expert-led analysis of AI, software, business and industry — written by practitioners and edited for depth, accuracy and usefulness.'),
     'primaryLabel':S('Explore stories'),'primaryUrl':S('/explore/'),'secondaryLabel':S('Write for Techvenza'),'secondaryUrl':S('/become-an-author/'),
     'statsMode':S('auto'),'stats':A([]),'showTopics':B(True),'topics':A(['ai','software','manufacturing','cybersecurity','business','data','cloud','startups'])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'textarea','label':'Headline'},{'key':'gradientText','type':'text','label':'Gradient words (appended)'},{'key':'lead','type':'textarea','label':'Introduction'},
     {'key':'primaryLabel','type':'text','label':'Primary button','panel':'Buttons'},{'key':'primaryUrl','type':'url','label':'Primary link','panel':'Buttons'},{'key':'secondaryLabel','type':'text','label':'Secondary button','panel':'Buttons'},{'key':'secondaryUrl','type':'url','label':'Secondary link','panel':'Buttons'},
     {'key':'statsMode','type':'select','label':'Stats','panel':'Stats','options':[{'value':'auto','label':'Automatic counts'},{'value':'manual','label':'Manual'},{'value':'none','label':'Hide'}]},
     {'key':'stats','type':'repeater','label':'Manual stats','panel':'Stats','showIf':{'statsMode':'manual'},'fields':[{'key':'value','type':'text','label':'Value'},{'key':'label','type':'text','label':'Label'}],'addLabel':'Add stat'},
     {'key':'showTopics','type':'toggle','label':'Show topic chips','panel':'Topics'},{'key':'topics','type':'categories','label':'Topics','panel':'Topics'}],'cover-image',surface=False)
blk('featured-grid','Featured stories','One large lead story with two supporting stories.',
    {'mode':S('auto'),'lead':N(0),'side':A([])},
    [{'key':'mode','type':'select','label':'Stories','options':[{'value':'auto','label':'Automatic (featured flag / Settings lead)'},{'value':'manual','label':'Pick stories'}]},{'key':'lead','type':'post','label':'Lead story','showIf':{'mode':'manual'}},{'key':'side','type':'posts','label':'Supporting stories (2)','showIf':{'mode':'manual'}}],'star-filled')
h,hc=HEAD('Trending','What readers are opening now','Ranked by reading activity over the past seven days.','All trending','/trending/')
blk('trending-list','Trending list','Ranked list of the most-read stories for a period.',dict(h,period=S('7d'),count=N(6),columns=N(2)),
    hc+[{'key':'period','type':'select','label':'Period','options':PERIODS},{'key':'count','type':'number','label':'Number of stories','min':3,'max':12},{'key':'columns','type':'number','label':'Columns','min':1,'max':2}],'chart-line')
h,hc=HEAD('Latest','Fresh from the newsroom','','View all stories','/explore/')
blk('latest-posts','Latest stories','Grid of the newest stories, optionally filtered by category.',dict(h,count=N(6),columns=N(3),excludeShown=B(True),categories=A(),variant=S('default')),
    hc+[{'key':'count','type':'number','label':'Number of stories','min':1,'max':24},{'key':'columns','type':'number','label':'Columns','min':1,'max':4},{'key':'excludeShown','type':'toggle','label':'Skip stories already shown above'},{'key':'categories','type':'categories','label':'Only these categories'},{'key':'variant','type':'select','label':'Card style','options':[{'value':'default','label':'Default'},{'value':'boxed','label':'Boxed'},{'value':'row','label':'Compact rows'}]}],'grid-view')
h,hc=HEAD('Explore by category','Find your field','','All categories','/explore/')
blk('category-tiles','Category tiles','Colourful category tiles with icon and story count.',dict(h,mode=S('homepage'),categories=A(),count=N(8),countType=S(''),compact=B(False),spacing=S('tight')),
    hc+[{'key':'mode','type':'select','label':'Categories','options':[{'value':'homepage','label':'Those marked “Show on homepage”'},{'value':'all','label':'All top-level categories'},{'value':'manual','label':'Choose'}]},{'key':'categories','type':'categories','label':'Categories','showIf':{'mode':'manual'}},{'key':'count','type':'number','label':'Maximum tiles','min':2,'max':24},{'key':'countType','type':'select','label':'Count','options':[{'value':'','label':'All stories'},{'value':'guide','label':'Guides only (links to filtered Explore)'}]},{'key':'compact','type':'toggle','label':'Compact tiles (no count)'}],'category')
SP = [{'value':'dark','label':'Dark spotlight with list'},{'value':'split','label':'Split with quote'},{'value':'grid','label':'Boxed card grid'},{'value':'tint','label':'Category tint band'}]
blk('category-spotlight','Category spotlight','Highlights one category in one of four compositions.',
    {'category':S('ai'),'extraCategories':A(),'variant':S('dark'),'eyebrow':S(''),'heading':S(''),'description':S(''),'quote':S(''),'linkLabel':S(''),'count':N(4)},
    [{'key':'category','type':'category','label':'Category'},{'key':'extraCategories','type':'categories','label':'Also include'},{'key':'variant','type':'select','label':'Layout','options':SP},
     {'key':'eyebrow','type':'text','label':'Eyebrow','panel':'Text'},{'key':'heading','type':'text','label':'Heading','panel':'Text'},{'key':'description','type':'textarea','label':'Description','panel':'Text'},{'key':'quote','type':'textarea','label':'Pull quote','panel':'Text','showIf':{'variant':'split'}},{'key':'linkLabel','type':'text','label':'Link label','panel':'Text'},{'key':'count','type':'number','label':'Stories','min':2,'max':6}],'format-aside')
h,hc=HEAD("Editor's Picks",'Worth your full attention','Selected by our editors for depth, originality and practical value.',"All editor's picks",'/editors-picks/')
blk('editors-picks',"Editor's picks","Mosaic of stories flagged as Editor's pick, with optional category filter chips.",dict(h,count=N(5),filter=B(False),excludeShown=B(False)),
    hc+[{'key':'count','type':'number','label':'Stories','min':2,'max':12},{'key':'filter','type':'toggle','label':'Category filter chips'},{'key':'excludeShown','type':'toggle','label':'Skip stories already shown'}],'awards',script='techvenza-live')
h,hc=HEAD('Deep dives & perspectives','Expert perspectives, in their own words','','More deep dives','/explore/?type=deep-dive')
blk('quote-cards','Deep dives (quote cards)','Quote cards built from each story’s first key point.',dict(h,types=A(['deep-dive','opinion']),count=N(3)),
    hc+[{'key':'types','type':'list','label':'Formats (comma-separated)','help':'deep-dive, analysis, guide, opinion, news'},{'key':'count','type':'number','label':'Cards','min':1,'max':9}],'format-quote')
h,hc=HEAD('Popular this week','Most read')
blk('popular','Popular this week','Most-read ranked list with an optional newsletter sidebar.',dict(h,count=N(6),period=S('7d'),showNewsletter=B(True)),
    hc+[{'key':'count','type':'number','label':'Stories','min':3,'max':12},{'key':'period','type':'select','label':'Period','options':PERIODS},{'key':'showNewsletter','type':'toggle','label':'Newsletter sidebar'}],'list-view')
h,hc=HEAD('Featured authors','The people behind the analysis','','Meet all authors','/authors/')
blk('featured-authors','Featured authors','Row of featured author cards.',dict(h,count=N(4)),hc+[{'key':'count','type':'number','label':'Authors','min':2,'max':8}],'groups')
FI_DEFAULT=[{'icon':'users','title':'Written by practitioners','text':'Engineers, operators, analysts and leaders sharing what they have learned firsthand.','soft':'#EFF6FF','ink':'#1D4ED8'},
 {'icon':'checkCircle','title':'Edited for accuracy','text':'Every submission is reviewed for clarity, sourcing and originality before publication.','soft':'#ECFDF5','ink':'#047857'},
 {'icon':'shield','title':'Independent by design','text':'Sponsored content, if any, is always labelled. Editorial decisions are never for sale.','soft':'#F3F0FF','ink':'#6D28D9'}]
blk('feature-items','Feature items','Icon + title + text items (community, benefits, principles, formats).',
    {'eyebrow':S('Our contributor community'),'heading':S('Practitioners first. Editors always.'),'lead':S('Techvenza pairs people doing the work with editors who help them say it clearly. Every article is reviewed before it is published.'),'align':S('center'),'columns':N(3),'style':S('plain'),'items':A(FI_DEFAULT)},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'lead','type':'textarea','label':'Introduction'},{'key':'align','type':'select','label':'Heading alignment','options':[{'value':'center','label':'Centre'},{'value':'left','label':'Left'}]},{'key':'columns','type':'number','label':'Columns','min':2,'max':4},
     {'key':'style','type':'select','label':'Item style','options':[{'value':'plain','label':'Icon + text'},{'value':'panel','label':'Panels with label'}]},
     {'key':'items','type':'repeater','label':'Items','panel':'Items','fields':[{'key':'icon','type':'icon','label':'Icon'},{'key':'label','type':'text','label':'Small label (panel style)'},{'key':'title','type':'text','label':'Title'},{'key':'text','type':'textarea','label':'Text'},{'key':'soft','type':'color','label':'Icon background'},{'key':'ink','type':'color','label':'Icon colour'}],'addLabel':'Add item'}],'screenoptions')
blk('cta-band','CTA band','Colourful call-to-action with steps (Become an Author).',
    {'eyebrow':S('Become an author'),'heading':S('Have expertise worth sharing? Write with us.'),'text':S('Apply to join our contributor community. Our editors work with you to sharpen your ideas — publication is always subject to editorial review.'),
     'primaryLabel':S('Apply to write'),'primaryUrl':S('/apply/'),'secondaryLabel':S('How it works'),'secondaryUrl':S('/become-an-author/'),'layout':S('split'),
     'steps':A(['Apply with your expertise and writing samples','Pitch or submit a draft through the author portal',"Revise with an editor's feedback",'Publish once the editorial team approves'])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'text','type':'textarea','label':'Text'},{'key':'primaryLabel','type':'text','label':'Primary button','panel':'Buttons'},{'key':'primaryUrl','type':'url','label':'Primary link','panel':'Buttons'},{'key':'secondaryLabel','type':'text','label':'Secondary button','panel':'Buttons'},{'key':'secondaryUrl','type':'url','label':'Secondary link','panel':'Buttons'},
     {'key':'layout','type':'select','label':'Layout','options':[{'value':'split','label':'Text + steps'},{'value':'center','label':'Centred'}]},{'key':'steps','type':'repeater','scalar':True,'label':'Steps','panel':'Steps','addLabel':'Add step'}],'megaphone')
blk('newsletter','Newsletter sign-up','Double opt-in newsletter form (compact or full width).',
    {'layout':S('full'),'wrap':B(True),'eyebrow':S('The Weekly Brief'),'heading':S('One thoughtful email a week.'),'text':S('The most useful stories on AI, software, business and industry — selected by our editors, with no sponsored filler.')},
    [{'key':'layout','type':'select','label':'Layout','options':[{'value':'full','label':'Full'},{'value':'compact','label':'Compact'}]},{'key':'wrap','type':'toggle','label':'Wrap in a page section'},{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'text','type':'textarea','label':'Text'}],'email')
blocks['newsletter']['attributes']['spacing']=S('tight')

# ---------- Pages ----------
blk('page-hero','Page hero','Breadcrumbs, title and introduction. In “automatic” mode it uses the page/archive title and hides itself when the page already starts with a hero.',
    {'auto':B(False),'eyebrow':S(''),'title':S(''),'lead':S(''),'tint':S(''),'breadcrumbs':B(True),'display':B(False)},
    [{'key':'auto','type':'toggle','label':'Automatic (use page or archive title)'},{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'title','type':'text','label':'Title'},{'key':'lead','type':'textarea','label':'Introduction'},{'key':'tint','type':'color','label':'Tint background'},{'key':'breadcrumbs','type':'toggle','label':'Breadcrumbs'},{'key':'display','type':'toggle','label':'Large editorial display title'}],'heading',surface=False)
blk('section','Section','A page section that holds normal blocks (paragraphs, lists, columns, buttons) at full container width.',
    {'eyebrow':S(''),'heading':S(''),'description':S(''),'narrow':B(False)},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'description','type':'textarea','label':'Description'},{'key':'narrow','type':'toggle','label':'Narrow (reading width)'}],'align-wide',
    extra={'tvInner':{'label':'section content','template':[['core/paragraph',{'placeholder':'Write the section content…'}]]}})
blk('split-hero','Split hero','Large headline and buttons beside a numbered steps card (e.g. Become an Author).',
    {'eyebrow':S('Contributor programme'),'title':S('Share Your Expertise with Techvenza.'),'lead':S('Reach readers who build, lead and invest in technology. Work with editors who help you sharpen your thinking and say it clearly.'),
     'primaryLabel':S('Apply to write'),'primaryUrl':S('/apply/'),'secondaryLabel':S('Read the guidelines'),'secondaryUrl':S('/editorial-guidelines/'),'caption':S('Publication is always subject to editorial review and approval.'),
     'steps':A(['Apply with your expertise','Get access to the author portal','Draft and submit articles','Revise with editor feedback','Publish after approval'])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'title','type':'textarea','label':'Title'},{'key':'lead','type':'textarea','label':'Introduction'},{'key':'primaryLabel','type':'text','label':'Primary button','panel':'Buttons'},{'key':'primaryUrl','type':'url','label':'Primary link','panel':'Buttons'},{'key':'secondaryLabel','type':'text','label':'Secondary button','panel':'Buttons'},{'key':'secondaryUrl','type':'url','label':'Secondary link','panel':'Buttons'},{'key':'caption','type':'text','label':'Caption'},{'key':'steps','type':'repeater','scalar':True,'label':'Steps','panel':'Steps','addLabel':'Add step'}],'cover-image',surface=False)
blk('process-steps','Process steps','Numbered process cards.',
    {'eyebrow':S('Process'),'heading':S('How submission works'),'steps':A([{'title':'Apply','text':'Tell us about your expertise and share writing samples.'},{'title':'Pitch or draft','text':'Use the author portal to draft an article or outline a pitch.'},{'title':'Editorial review','text':'An editor reviews for accuracy, clarity, originality and fit.'},{'title':'Revise & publish','text':'Address feedback. Approved articles are scheduled for publication.'}])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'steps','type':'repeater','label':'Steps','panel':'Steps','fields':[{'key':'title','type':'text','label':'Title'},{'key':'text','type':'textarea','label':'Text'}],'addLabel':'Add step'}],'editor-ol')
blk('faq','FAQ','Accordion of questions and answers with FAQPage structured data.',
    {'heading':S('Frequently asked questions'),'items':A([]),'narrow':B(True)},
    [{'key':'heading','type':'text','label':'Heading'},{'key':'narrow','type':'toggle','label':'Narrow width'},{'key':'items','type':'repeater','label':'Questions','panel':'Questions','fields':[{'key':'q','type':'text','label':'Question'},{'key':'a','type':'textarea','label':'Answer'}],'addLabel':'Add question'}],'editor-help')
blk('team-grid','Team grid','Editorial team members.',
    {'eyebrow':S('Editorial leadership'),'heading':S('The editorial team'),'description':S(''),'members':A([])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'description','type':'textarea','label':'Description'},{'key':'members','type':'repeater','label':'Members','panel':'Members','fields':[{'key':'name','type':'text','label':'Name'},{'key':'role','type':'text','label':'Role'},{'key':'photo','type':'image','label':'Photo'},{'key':'url','type':'url','label':'Profile link'}],'addLabel':'Add member'}],'groups')
blk('editor-note',"Editor's note",'Serif pull note with attribution.',{'text':S(''),'attribution':S('')},[{'key':'text','type':'textarea','label':'Note'},{'key':'attribution','type':'text','label':'Attribution'}],'format-quote')
blk('picks-hero',"Editor's Picks hero",'Dark editorial hero with the lead pick.',
    {'eyebrow':S("Editor's Picks"),'title':S("Stories we think you shouldn't miss."),'lead':S('Each week our editors choose the pieces with the most original thinking and practical value. Here is the current selection.'),'post':N(0)},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'title','type':'textarea','label':'Title'},{'key':'lead','type':'textarea','label':'Introduction'},{'key':'post','type':'post','label':'Lead story','help':'Empty = latest editor’s pick.'}],'awards',surface=False)
blk('format-columns','Format columns','Columns of stories by format (in-depth, analysis, guides…).',
    {'columns':A([{'title':'In-depth stories','icon':'book','types':['deep-dive'],'orderby':'views'},{'title':'Expert analysis','icon':'chart','types':['analysis','opinion'],'orderby':'views'},{'title':'Evergreen guides','icon':'compass','types':['guide'],'orderby':'views'}]),'count':N(3)},
    [{'key':'count','type':'number','label':'Stories per column','min':1,'max':8},{'key':'columns','type':'repeater','label':'Columns','panel':'Columns','fields':[{'key':'title','type':'text','label':'Title'},{'key':'icon','type':'icon','label':'Icon'},{'key':'types','type':'list','label':'Formats'},{'key':'categories','type':'categories','label':'Only these categories'},{'key':'orderby','type':'select','label':'Order','options':[{'value':'views','label':'Most read'},{'value':'date','label':'Newest'}]}],'addLabel':'Add column'}],'columns')
blk('series-cards','Featured series','Cards for multi-part series (Series taxonomy or manual).',
    {'eyebrow':S('Featured series'),'heading':S('Read it in order'),'description':S('Multi-part series that build a topic from first principles.'),'mode':S('auto'),'items':A([])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'description','type':'textarea','label':'Description'},{'key':'mode','type':'select','label':'Source','options':[{'value':'auto','label':'Series taxonomy'},{'value':'manual','label':'Manual cards'}]},
     {'key':'items','type':'repeater','label':'Cards','panel':'Cards','showIf':{'mode':'manual'},'fields':[{'key':'title','type':'text','label':'Title'},{'key':'parts','type':'text','label':'Parts label'},{'key':'text','type':'textarea','label':'Description'},{'key':'category','type':'category','label':'Accent category'},{'key':'url','type':'url','label':'Link'}],'addLabel':'Add card'}],'images-alt2')
blk('learning-paths','Learning paths','Curated reading sequences.',
    {'eyebrow':S('Learning paths'),'heading':S('Curated learning paths'),'description':S('Read in sequence to build a topic step by step.'),'paths':A([])},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'description','type':'textarea','label':'Description'},{'key':'paths','type':'repeater','label':'Paths','panel':'Paths','fields':[{'key':'title','type':'text','label':'Title'},{'key':'level','type':'text','label':'Level label'},{'key':'category','type':'category','label':'Accent category'},{'key':'posts','type':'posts','label':'Articles in order'}],'addLabel':'Add path'}],'randomize')
blk('guides-grid','Guides grid','How-to guides with level tabs.',{'heading':S('How-to guides'),'count':N(12)},[{'key':'heading','type':'text','label':'Heading'},{'key':'count','type':'number','label':'Maximum guides','min':3,'max':24}],'welcome-learn-more',script='techvenza-live')
blk('category-rankings','Trending by category','Top stories per category, side by side.',
    {'eyebrow':S('By category'),'heading':S('Trending by category'),'categories':A(['ai','software','cybersecurity','business']),'count':N(3)},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'categories','type':'categories','label':'Categories'},{'key':'count','type':'number','label':'Stories each','min':1,'max':6}],'chart-bar')

# ---------- Interactive listings ----------
blk('explore','Explore (filterable archive)','Search, category/topic/format filters, sort and pagination. Filters are URL parameters (shareable, crawlable) and update in place.',
    {'perPage':N(9),'showSidebar':B(True),'showFeatured':B(True)},
    [{'key':'perPage','type':'number','label':'Stories per page','min':3,'max':30},{'key':'showSidebar','type':'toggle','label':'Trending + newsletter sidebar'},{'key':'showFeatured','type':'toggle','label':'Featured story on page one'}],'filter',script='techvenza-live',surface=False)
blk('trending-page','Trending (ranked page)','Lead story, most-read panel and ranked list with a period switcher.',{'count':N(10)},[{'key':'count','type':'number','label':'Ranked stories','min':5,'max':25}],'chart-line',script='techvenza-live',surface=False)
blk('author-directory','Author directory','Featured contributors plus searchable, filterable author cards with load more.',{'perPage':N(6),'showFeatured':B(True)},[{'key':'perPage','type':'number','label':'Authors per page','min':3,'max':24},{'key':'showFeatured','type':'toggle','label':'Featured contributors row'}],'groups',script='techvenza-live',surface=False)
blk('search-results','Search results','Search heading, input, result tabs (all / articles / authors / topics) with highlighted matches.',{},[],'search',script='techvenza-live',surface=False)
blk('archive-grid','Archive grid','Posts for the current archive (tag, date, series, author) with pagination.',{'perPage':N(9)},[{'key':'perPage','type':'number','label':'Posts per page','min':3,'max':30}],'grid-view',surface=False)
blk('not-found','404 content','Branded not-found page with search and suggestions.',{'title':S('This page wandered off.'),'text':S('The link may be broken or the page may have moved. Try searching, or head somewhere useful below.')},[{'key':'title','type':'text','label':'Title'},{'key':'text','type':'textarea','label':'Text'}],'warning',surface=False)

# ---------- Category template ----------
blk('category-hero','Category hero','Tinted hero using the category colour, icon and intro, with follow button and subcategory chips.',{},[],'category',surface=False)
blk('category-featured','Category featured + trending','Lead story and trending list for the current category.',{'count':N(4)},[{'key':'count','type':'number','label':'Trending stories','min':2,'max':8}],'star-half',surface=False)
blk('category-latest','Category latest','Paginated latest stories in the category with subcategory filter.',{'perPage':N(6)},[{'key':'perPage','type':'number','label':'Per page','min':3,'max':24}],'grid-view',script='techvenza-live',surface=False)
blk('category-extras','Category tags, related & authors','Topic tags, related categories and authors writing in this category.',{},[],'tag',surface=False)

# ---------- Article template ----------
blk('reading-progress','Reading progress bar','Thin progress bar at the top of the viewport for articles.',{},[],'minus',surface=False,extra={'tvPreview':'placeholder'})
blk('article-header','Article header','Breadcrumbs, category, headline, summary, byline, dates, reading time and article tools.',{'showTools':B(True)},[{'key':'showTools','type':'toggle','label':'Share / save / text size / dark mode tools'}],'heading',script='techvenza-article',surface=False)
blk('article-figure','Article hero image','Featured image with caption (or branded placeholder).',{},[],'format-image',surface=False)
blk('article-layout','Article layout','Three-column reading layout: sticky table of contents, article body and trending rail.',{'showToc':B(True),'showRail':B(True)},
    [{'key':'showToc','type':'toggle','label':'Table of contents'},{'key':'showRail','type':'toggle','label':'Trending rail'}],'columns',script='techvenza-article',surface=False,
    extra={'tvInner':{'label':'article body and end matter','template':[['core/post-content',{'className':'prose tv-article-body','layout':{'type':'default'}}],['techvenza/article-end',{}]]}})
blk('article-end','Article end matter','Tags, corrections, disclosures, share row, author bio, more from author and newsletter.',{'showNewsletter':B(True),'showMore':B(True)},[{'key':'showNewsletter','type':'toggle','label':'Newsletter'},{'key':'showMore','type':'toggle','label':'More from this author'}],'editor-insertmore',surface=False)
blk('related-posts','Related stories','Stories sharing the category or tags of the current article.',{'heading':S('Related stories'),'count':N(3)},[{'key':'heading','type':'text','label':'Heading'},{'key':'count','type':'number','label':'Stories','min':2,'max':6}],'admin-links')
blk('toc','Table of contents','Auto-generated from H2/H3 headings on the page, with active-section highlighting.',{'title':S('On this page')},[{'key':'title','type':'text','label':'Title'}],'list-view',script='techvenza-article',surface=False)
blk('doc-layout','Document with side navigation','Legal/guideline layout: side navigation built from the H2 headings inside, optional draft banner.',{'draftBanner':B(False)},[{'key':'draftBanner','type':'toggle','label':'Show “draft — requires legal review” banner','help':'Also controlled globally in Settings → Privacy.'}],'media-document',script='techvenza-article',surface=False,
    extra={'tvInner':{'label':'document content (use H2 headings for sections)','template':[['core/heading',{'level':2,'content':'Section'}],['core/paragraph',{}]]}})
blk('author-profile','Author profile','Author header (photo, title, bio, expertise, links) with featured and latest articles.',{},[],'admin-users',surface=False)

blk('partners','Partners','Logos and links of partner companies managed in Editorial → Partners (link type and expiry per partner).',
    {'eyebrow':S('Partners'),'heading':S('Companies we work with'),'description':S(''),'count':N(12),'showDescription':B(True)},
    [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'heading','type':'text','label':'Heading'},{'key':'description','type':'textarea','label':'Description'},{'key':'count','type':'number','label':'Maximum partners','min':1,'max':48},{'key':'showDescription','type':'toggle','label':'Show descriptions'}],'groups',
    extra={'tvHint':'Add, order and expire partners in Editorial → Partners.'})

# ---------- Forms & accounts ----------
F = lambda n,t,d,a=None,c=None,icon='feedback': blk(n,t,d,a or {},c or [],icon,script='techvenza-forms',surface=False)
F('apply-form','Author application form','Contributor application with validation, spam protection and confirmation email.',{'intro':S('')},[{'key':'intro','type':'textarea','label':'Introduction'}])
F('contact-form','Contact form','Inquiry form routed by type to the emails in Settings → Forms & Spam.',{'showAside':B(True)},[{'key':'showAside','type':'toggle','label':'Side information panels'}],'email-alt')
F('newsletter-signup','Newsletter sign-up page','Full sign-up with topic and frequency choice, consent and double opt-in.',{'eyebrow':S('The Weekly Brief'),'title':S('The week in technology, edited.'),'lead':S("Every Thursday: the stories worth your time, a short editor's note, and one deep dive to save for the weekend. No sponsored filler.")},
  [{'key':'eyebrow','type':'text','label':'Eyebrow'},{'key':'title','type':'text','label':'Title'},{'key':'lead','type':'textarea','label':'Introduction'}],'email')
def AUTH(side_title, side_text, heading, intro):
    a = {'sideTitle':S(side_title),'sideText':S(side_text),'heading':S(heading),'intro':S(intro)}
    c = [{'key':'heading','type':'text','label':'Heading','panel':'Content'},{'key':'intro','type':'textarea','label':'Intro','panel':'Content'},
         {'key':'sideTitle','type':'text','label':'Side panel title','panel':'Side panel'},{'key':'sideText','type':'textarea','label':'Side panel text','panel':'Side panel'}]
    return a, c
F('login-form','Login form','Branded login with rate limiting, captcha and optional Google sign-in.',*AUTH('Your reading list, topics and drafts in one place.','Sign in to save stories, follow topics and — for authors — manage submissions.','Welcome back',''),'lock')
F('register-form','Registration form','Reader registration with email verification.',*AUTH('Read smarter. Follow what matters.','A free account lets you save stories, follow topics and apply to become a contributor.','Create your account',''),'id')
F('forgot-form','Forgot password form','Requests a password reset email.',*AUTH('Locked out? It happens.','Enter your email and we will send a secure link to reset your password.','Forgot your password?','Enter the email you use for Techvenza.'),'unlock')
F('reset-form','Reset password form','Sets a new password from a reset link (handles expired links).',*AUTH('Choose a strong new password.','Use a password manager if you can — and turn on passkeys when your device supports them.','Set a new password',''),'unlock')
blk('reader-dashboard','Reader dashboard','Private reader area: saved, history, topics, newsletter preferences, profile.',{},[],'dashboard',script='techvenza-portal',surface=False,extra={'tvPreview':'placeholder','tvHint':'Only visible to signed-in readers. Assign the “App screen” template to this page.'})
blk('author-portal','Author portal','Contributor workspace: dashboard, articles, submissions, profile, analytics, notifications, settings.',{},[],'edit-page',script='techvenza-portal',surface=False,extra={'tvPreview':'placeholder','tvHint':'Only visible to authors and editors. Assign the “App screen” template to this page.'})

POST_CONTEXT = {'reading-progress','article-header','article-figure','article-layout','article-end','related-posts','toc'}
for name, j in blocks.items():
    if name in POST_CONTEXT:
        j['usesContext'] = ['postId', 'postType']
    d = os.path.join(OUT, name); os.makedirs(d, exist_ok=True)
    if j['attributes'] is None: j['attributes'] = {}
    j['attributes'] = {k:v for k,v in j['attributes'].items()}
    with open(os.path.join(d,'block.json'),'w') as f:
        json.dump(j, f, indent='\t', ensure_ascii=False); f.write('\n')
print(len(blocks), 'blocks')
