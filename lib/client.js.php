<?php
// The client bundle source. Served only through assets/app.js.php.
// The .php extension plus this guard mean a direct request is refused
// by any web server, not just one that honours .htaccess.
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }
?>
/* ---------------------------------------------------------------
   Translations
   Loaded first so the drawing code can call t() during its very first
   render. Anything shown to the user goes through this table; strings
   left in the markup are only the English fallback.
   --------------------------------------------------------------- */

const I18N={
  en:{
    'app.title':'Honza3D · Drawer Organizer Studio',
    'app.subtitle':'Drawer Organizer Studio',

    'theme.light':'Light',
    'units':'Units',

    'btn.new':'Delete layout',
    'btn.autofill':'Fill 1×1 boxes',
    'btn.export':'Export',
    'btn.delete':'Delete Selected Box',
    'btn.random':'Generate Random Layout',
    'btn.reset':'Reset to 1×1 Grid',
    'btn.mixed':'Create Mixed Layout',

    'export.3mf.label':'3MF package',
    'export.3mf.desc':'Every box stays a separate object. Best for Bambu Studio and OrcaSlicer.',
    'export.stl.label':'Binary STL',
    'export.stl.desc':'All boxes in one file as separate shells. Use Split to Parts after import.',
    'export.note':'The 3D file is always at real size in millimetres (slicers read it as mm). The display unit only changes what you see in the editor.',

    'panel.drawer':'Drawer',
    'panel.print':'Print area',
    'panel.sizecon':'Size & construction',
    'btn.fix':'Fix',
    'panel.construction':'Construction',
    'panel.grid':'Grid',
    'panel.selected':'Selected Box',
    'panel.layout':'Layout',
    'panel.random':'Random Layout',
    'panel.inspector':'Inspector',
    'panel.quick':'Quick Layouts',
    'panel.howto':'How To',

    'field.width':'Width',
    'field.depth':'Depth',
    'field.height':'Height',
    'field.wall':'Wall',
    'field.bottom':'Bottom',
    'field.radius':'Corner radius',
    'field.maxprint':'Max print area',
    'field.maxprint.wd':'width × depth',
    'field.maxprint.hint':'0 = no limit. A box larger than the printer bed is flagged and cannot be exported.',
    'field.gap':'Gap',
    'field.outer':'Outer inset',
    'field.outer.hint':'0 = boxes reach the drawer walls.',

    // The explanations behind the little "i" next to each setting.
    'tip.dw':'Inside width of the drawer, measured wall to wall - not the width of the front panel.',
    'tip.dd':'Inside depth of the drawer, from the front wall to the back one.',
    'tip.dh':'How tall the boxes are. Keep it under the drawer opening, or the drawer will not close.',
    'tip.wall':'Thickness of the box walls. Two millimetres is a sturdy print in PLA or PETG; more only costs plastic and time.',
    'tip.bottom':'Thickness of the floor. Same idea as the wall - thicker only if something heavy goes in.',
    'tip.radius':'How much the corners are rounded. Rounded corners print more cleanly and are easier to wipe out.',
    'tip.gap':'Space left BETWEEN neighbouring boxes, so they do not rub against each other. Half of it comes off each box.',
    'tip.outer':'Margin left free all the way round the drawer. Set 1 mm and a 250 mm drawer is filled as if it were 248 - the finished set drops in instead of having to be pressed.',
    'tip.printw':'Width of your printer bed. A box wider than this is flagged in red and cannot be exported. 0 = no limit.',
    'tip.printd':'Depth of your printer bed. Rotation is allowed, so a box fits if it fits either way round. 0 = no limit.',
    'tip.cols':'How many columns the drawer is divided into. The grid is the raster boxes snap to; the cell size follows from it.',
    'tip.rows':'How many rows the drawer is divided into.',
    'tip.minw':'The narrowest box the generator may make, in cells. At 100 percent fill it still uses smaller ones for the leftover gaps.',
    'tip.maxw':'The widest box the generator may make, in cells.',
    'tip.mind':'The shallowest box the generator may make, in cells.',
    'tip.maxd':'The deepest box the generator may make, in cells.',
    'tip.fill':'How much of the drawer to fill. 100 percent leaves no empty cell.',
    'tip.variation':'The character of the result: balanced, mixed, mostly large or mostly small boxes.',
    'field.columns':'Columns',
    'field.rows':'Rows',
    'field.wcells':'Width (cells)',
    'field.dcells':'Depth (cells)',
    'field.minw':'Min width (cells)',
    'field.maxw':'Max width (cells)',
    'field.mind':'Min depth (cells)',
    'field.maxd':'Max depth (cells)',
    'field.fill':'Fill level',
    'field.variation':'Variation',
    'field.freemode':'FREE MODE',
    'field.freemode.hint':'Some boxes come out as L and T shapes.',

    'var.balanced':'Balanced',
    'var.mixed':'Mixed',
    'var.large':'Large boxes',
    'var.small':'Small boxes',

    'random.note':'Generates a new arrangement that fits the current grid.',
    'random.undersized':'{0} gap(s) were too small for the minimum size.',
    'step.hint':'Hold Shift for a fine step',
    'acct.credits':'Account & credits',
    'acct.admin':'Administration',
    'acct.signout':'Sign out',
    'acct.signin':'Sign in',
    'aria.language':'Language',
    'aria.units':'Display units',
    'locked.title':'With an account',
    'locked.body':'Generate random arrangements with your own minimum and maximum box sizes, fill level and style.',
    'foot.register':'Create an account','foot.account':'My account','foot.cookies':'Cookies',
    'signin.title':'Sign in',
    'signin.lede':'Credits and redeemed codes are tied to your account.',
    'signin.email':'Email',
    'signin.password':'Password',
    'signin.submit':'Sign in',
    'signin.cancel':'Cancel',
    'signin.full':'Open the full page',
    'signin.forgot':'Forgotten password?',
    'signin.missing':'Fill in both fields.',
    'signin.failed':'Could not sign in. Check the address and password.',
    'foot.units':'All dimensions in {0}.',
    'unit.mm.long':'millimetres','unit.cm.long':'centimetres','unit.in.long':'inches',
    'aria.decrease.dw':'Decrease width',
    'aria.increase.dw':'Increase width',
    'aria.decrease.dd':'Decrease depth',
    'aria.increase.dd':'Increase depth',
    'aria.decrease.dh':'Decrease height',
    'aria.increase.dh':'Increase height',
    'seed':'Seed',

    'stat.boxes':'Boxes',
    'stat.used':'Used cells',
    'stat.free':'Free cells',
    'stat.status':'Status',
    'status.ready':'READY',
    'status.invalid':'INVALID',
    'err.tooshort':'The box has to be at least {0} tall: the floor is {1} and at least 1 mm has to be left above it.',

    'howto.text':'Drag a box to move it. Drag any edge or corner to resize it. Boxes snap to the grid and cannot overlap. Click a box to select it, then use the red button inside it to remove it.',

    'box.remove':'Remove box',
    'box.grow':'Grow into the next cell','box.shrink':'Shrink by one cell',
    'box.addcell':'Claim this cell','box.rmcell':'Give this cell back',
    'box.addwall':'Put a divider on this line','box.addmidwall':'Split this box exactly in half','box.rmwall':'Remove this divider',
    'box.deselect':'Deselect',
    'err.wallfloats':'A divider cannot stop in mid-air. Run it into the box wall or into another divider.',
    'walls.disable.title':'Disable divider mode?',
    'walls.disable.body':'Turning off divider mode will delete all dividers you have placed. This cannot be undone.',
    'walls.disable.ok':'Delete dividers',
    'walls.mode.label':'Box editing mode',
    'walls.mode.walls':'Walls',
    'walls.grid.label':'Divider grid detail',
    'walls.grid.coarse':'1 : 1 · standard',
    'walls.grid.fine':'2 : 1 · fine',
    'walls.grid.freeHint':'Switch to Walls to change divider grid detail.',
    'walls.grid.note':'FREE MODE is available only when divider mode is off.',
    'walls.shapesOnlyOff':'Turn off divider mode before editing a FREE MODE shape.',
    'walls.mode.free':'FREE MODE',
    'walls.mode.switchTitle':'Switch editing mode?',
    'walls.mode.toFree':'Free mode removes the dividers currently drawn in this layout. The boxes themselves stay in place.',
    'walls.mode.toWalls':'Wall mode replaces FREE MODE with divider editing. A FREE MODE shape has to be changed back before walls can be enabled.',
    'walls.mode.toWallsPoly':'Wall mode will convert every FREE MODE shape into adjoining rectangular boxes. No cells are lost; Undo restores the shapes.',
    'walls.mode.switchOk':'Switch mode',
    'walls.mode.converted':'Converted {0} free shape(s) into {1} rectangular box(es) for Wall mode.',
    'walls.grid.switchTitle':'Switch divider grid detail?',
    'walls.grid.toFine':'The same layout will be edited on the finer 2:1 divider grid.',
    'walls.grid.toCoarse':'Partial 2:1 dividers will be removed. Only two matching halves become a full 1:1 divider.',
    'walls.grid.switchOk':'Switch grid',
    'walls.grid.coarsened':'Fine divider segments were converted to {0} full divider(s); incomplete segments were removed.',
    'box.cellcount':'{0} cells','err.polyhole':'The shape would enclose a hole - fill it or grow around it differently.',
    'box.clone':'Duplicate box',

    'spend.title':'Free export used up',
    'spend.body':'This export will use {0} credit(s). You have {1} and will be left with {2}.',
    'spend.ok':'Use a credit',
    'spend.cancel':'Cancel',

    'layout.clearTitle':'Delete layout?',
    'layout.clearBody':'This removes every box from the drawer. It cannot be undone.',
    'layout.clearOk':'Delete layout',
    'layout.fillTitle':'Replace current layout?',
    'layout.fillBody':'Filling with 1×1 boxes deletes the current layout first. It cannot be undone.',
    'layout.fillOk':'Delete and fill',

    'err.fixlayout':'Fix the layout before exporting.',
    'err.nobox':'There are no boxes to export.',
    'err.oversize':'Some boxes are larger than the max print area. Shrink them or raise the limit.',
    'hint.printlimit':'Max print area reached - the box can’t grow further. Raise the limit or add a grid row/column.',
    'err.toomany':'Too many boxes for one export. Use Fix to trim them, or lower the count.',
    'err.colnotempty':'That column has boxes in it - clear it first, then it can be removed.',
    'err.rownotempty':'That row has boxes in it - clear it first, then it can be removed.',
    'err.overflow':'Some boxes fall outside the grid - move or resize them to fit.',
    'err.failed':'Export failed.',
    'err.http':'Export failed ({0}).',

    'quota.free':'A free export is available.',
    'quota.freeNow':'Export is free right now',
    'quota.freeEvery':'One free export every {0}.',
    'quota.freeLeft':'{0} more free, then one every {1}.',
    'quota.freeCount':'{0} of {1} free exports left',
    'quota.exhausted':'Free exports used up',
    'quota.allowance':'{0} free every {1}.',
    'quota.nextBack':'One free export comes back in {0}.',
    'quota.resetIn':'One comes back in {0}.',
    'quota.resetAll':'The allowance resets in {0}.',
    'quota.costs':'This export costs {0} credit(s)',
    'quota.nextFree':'Free again in {0}.',
    'quota.everyCosts':'Every export uses credits.',
    'quota.needCredits':'Credits needed to export',
    'quota.waitFor':'Free again in {0}',
    'quota.balance':'Balance: {0} credit(s)',
    'quota.credit':'Next free export in {0}. Exporting now costs {1} credit(s).',
    'quota.blocked':'Next free export in {0}.',
    'quota.login':'Sign in to export',
    'quota.nocredits':'You have no credits left.',
    'quota.signin':'Sign in',
    'quota.getcredits':'Get credits',
    'quota.charged':'{0} credit(s) used. {1} left.',
    'quota.freeShort':'{0}/{1} free',
    'quota.freeExports':'Free exports','quota.remaining':'{0} of {1} remaining',
    'quota.upgrade':'Upgrade',
    'nav.home':'Home','nav.generate':'Generate','nav.edit':'Edit layout','nav.boxdetails':'Box details','nav.export':'Export','nav.share':'Share','nav.load':'Load project','nav.howto':'How to use','nav.about':'About','nav.collapse':'Collapse menu','nav.expand':'Expand menu','nav.dims':'Drawer size','nav.print':'Print area','nav.grid':'Grid','nav.layout':'Layout','nav.inspector':'Overview','nav.account':'Account','nav.admin':'Administration','nav.signout':'Sign out','nav.signin':'Sign in','nav.prefs':'Preferences','nav.quickSettings':'Quick settings','pref.units':'Units','pref.language':'Language','pref.theme':'Theme','onboard.title':'Welcome to the studio','onboard.lede':'Choose how the studio should look. You can change any of this later under Preferences.','onboard.language':'Language','onboard.units':'Units','onboard.theme':'Theme','onboard.start':'Start designing','export.json.label':'Save project','export.json.desc':'Download the layout as a file you can load again later.','export.json.locked':'Sign in to save and share your project.','lock.title':'Available after sign in','lock.print':'Set your printer bed so a box that will not fit is flagged before export.','lock.layout':'Auto-fill the drawer with boxes by your own rules - min/max size, fill level and style.','lock.inspector':'Live overview: box count, used and free cells, validity and a one-click Fix.','lock.load':'Open a saved project file and pick up your design where you left off.','lock.walls':'Split boxes into compartments with internal dividers. Set their positions directly on the box grid; the dividers are printed as part of the box.',
    'ct.drawer':'Drawer size','ct.selected':'Selected box','credits.title':'Credits and exports','credits.balance':'Credits','credits.free':'Free exports','credits.reset':'Allowance resets in','credits.cost':'Price per export','credits.note':'Free exports are used first; credits only when they run out.','credits.manage':'Manage account and credits','credits.unlimited':'Unlimited','credits.cr':'credits',
    'status.dims':'Drawer size','status.boxes':'Total boxes','status.cells':'Used cells','status.saved':'Last saved','status.now':'Just now','status.minsAgo':'{0} min ago','status.hoursAgo':'{0} h ago',
    'tool.select':'Select and edit','tool.pan':'Pan the canvas','tool.undo':'Undo (Ctrl+Z)','tool.redo':'Redo (Ctrl+Shift+Z)','tool.grid':'Toggle grid','tool.labels':'Toggle labels','tool.fit':'Fit to view','tool.zoomout':'Zoom out','tool.zoomin':'Zoom in','tool.center':'Center view','share.saved':'Layout saved as a file','share.title':'Share project','share.desc':'Create a link without project data in the URL. The design is stored under a secure key.','share.locked':'Sign in to create a share link for the project.','share.created':'Share link copied to clipboard','share.popup.title':'Share link','share.popup.copied':'The link was copied to the clipboard.','share.loaded':'Shared project loaded','share.error':'The share link could not be created or loaded.','load.bad':'Could not read that project file','share.popup.copy':'Copy link','share.popup.copyHint':'Copy it with the button below.','share.preview.title':'Shared design','share.preview.desc':'Someone sent you this design. Have a look first - it does not go into the studio until you say so.','share.preview.summary':'Drawer {0} · {1} boxes · grid {2} × {3}','share.preview.warn':'Taking it over replaces the design you have open now, and that cannot be undone.','share.preview.empty':'Your drawer is empty, so nothing of yours is lost.','share.preview.now':'Now','share.preview.after':'After taking it','share.preview.close':'Close','share.preview.take':'Take the design','share.preview.taken':'Shared design taken over',
    'help.intro':'Design a custom drawer organizer and export it as a print-ready 3MF or STL file. Everything updates live as you change it.',
    'help.dims.t':'Drawer size','help.dims.b':'Measure the drawer inside, not the front, and type the width, depth and height. Below them sit wall thickness, floor thickness, corner radius and the gap between boxes - the gap is why finished boxes are a little smaller than the cell they stand in, and why they drop in instead of wedging.',
    'help.grid.t':'Grid','help.grid.b':'Columns and rows are the raster every box snaps to. Changing them keeps what you have already drawn. The + and - squares around the edge of the drawing add or remove a whole column or row on that side; a column that still holds a box will not go.',
    'help.edit.t':'Drawing and editing','help.edit.b':'Click an empty cell to put a 1x1 box there. Drag a box to move it, drag an edge or a corner to resize it - boxes snap to the grid and never overlap. A selected box gets a bubble beside it with its size, a duplicate button and a bin, and controls for changing its dimensions.',
    'help.generate.t':'Generator','help.generate.b':'Fills the whole drawer for you. Set the smallest and largest box in cells, how full it should be and the style, then Generate; every run comes out different. At 100 percent the leftover gaps are taken by smaller boxes than the minimum, because a hole one cell wide can hold nothing else.',
    'help.tools.t':'Tools under the drawing','help.tools.b':'The arrow selects and edits; the hand moves the canvas and lets go of the selected box. Then the bin that clears the whole layout, undo and redo, the zoom stepper, centre the view, and fit the whole design on screen.',
    'help.print.t':'Print area and fixing','help.print.b':'Enter your printer bed and the studio stops you from making a box that will not fit on it: it turns red, export is blocked and the Overview icon gets a red mark. Its Fix button adds columns and rows until everything fits, without touching the drawer size.',
    'help.export.t':'Export','help.export.b':'3MF keeps every box as a separate object, which is what Bambu Studio and OrcaSlicer like best. STL puts them in one file as separate shells. The model is always built at its real size in millimetres, whatever unit the editor is showing you.',
    'help.project.t':'Save and load a project','help.project.b':'Save project writes the whole design into a small file - drawer, grid, boxes and settings. Load project reads it back, so you can carry on another day or send the design to somebody else.',
    'help.pro.t':'What a signed-in account adds',
    'help.pro.1':'The generator, the print-area check and the Overview with live figures and one-click Fix.',
    'help.pro.2':'Undo and redo of the last five steps, in the strip under the drawing (Ctrl+Z, Ctrl+Shift+Z).',
    'help.pro.3':'The + and - handles for growing and shrinking the grid.',
    'help.pro.5':'Saving and loading a project as a file.',
    'help.pro.6':'Your drawer, grid, print area and current layout are kept in your profile and come back next time - the account icon turns green when everything is saved.',
    'help.pro.7':'More free exports than a visitor gets, plus credits, codes and time passes.',
    'foot.terms':'Terms of use',
    'confirm.dontask':'Do not remind me again',
    'news.title':'What is new','news.ok':'Got it',
    'pre.title':'One look before you print',
    'pre.body':'The file is built from the numbers you typed, so it is worth opening it in your slicer first: does the drawer size match what you measured inside the drawer, does every box sit on the plate, and are the walls and dividers where you meant them to be? A test print of one small box saves a lot of plastic if something is off.',
    'pre.ok':'Got it, download',
    'help.pro.8':'Dividers inside a box, from the wall button under the drawing.',
    'help.walls.t':'Dividers inside a box',
    'help.walls.b':'The wall button under the drawing turns a box into something you draw walls in: every line between two of its cells offers a plus, and a click puts a divider there. It is one wall thick, exactly the thickness set in Construction, and it is printed as part of the box - so a box divided into four is one piece, not four. Each end has to run into the outer wall or into another divider; one left hanging in mid-air is reported in the Overview and Fix removes it. Dividers use square internal corners, while the outer corners of the box can stay rounded.',
    'tool.walls':'Dividers in a box','aria.tools':'Tools','aria.history':'Undo and redo','aria.zoom':'Zoom',
    'help.account.t':'Account and credits','help.account.b':'Every export first uses your free allowance; once that runs out it costs a credit. If a daily grant is switched on, the amount and its ceiling are in the limits at the top of this help. The counter inside Export says what is left, and credits are topped up on your account page or with a code. The limits that apply right now are at the top of this help.'
  },

  cs:{
    'app.title':'Honza3D · Studio pořadačů do zásuvek',
    'app.subtitle':'Studio pořadačů do zásuvek',

    'theme.light':'Světlý',
    'units':'Jednotky',

    'btn.new':'Smazat layout',
    'btn.autofill':'Vyplnit boxy 1×1',
    'btn.export':'Export',
    'btn.delete':'Smazat vybraný box',
    'btn.random':'Vygenerovat rozvržení',
    'btn.reset':'Zpět na mřížku 1×1',
    'btn.mixed':'Vytvořit smíšené rozvržení',

    'export.3mf.label':'Balíček 3MF',
    'export.3mf.desc':'Každý box zůstane samostatný objekt. Nejlepší pro Bambu Studio a OrcaSlicer.',
    'export.stl.label':'Binární STL',
    'export.stl.desc':'Všechny boxy v jednom souboru jako samostatné skořepiny. Po importu použij Split to Parts.',
    'export.note':'3D soubor je vždy ve skutečné velikosti v milimetrech (slicer ho čte jako mm). Zvolená jednotka mění jen to, co vidíš v editoru.',

    'panel.drawer':'Zásuvka',
    'panel.print':'Tisková plocha',
    'panel.sizecon':'Velikost a konstrukce',
    'btn.fix':'Opravit',
    'panel.construction':'Konstrukce',
    'panel.grid':'Mřížka',
    'panel.selected':'Vybraný box',
    'panel.layout':'Generátor',
    'panel.random':'Náhodné rozvržení',
    'panel.inspector':'Inspektor',
    'panel.quick':'Rychlá rozvržení',
    'panel.howto':'Nápověda',

    'field.width':'Šířka',
    'field.depth':'Hloubka',
    'field.height':'Výška',
    'field.wall':'Stěna',
    'field.bottom':'Dno',
    'field.radius':'Zaoblení rohů',
    'field.maxprint':'Max. tisková plocha',
    'field.maxprint.wd':'šířka × hloubka',
    'field.maxprint.hint':'0 = bez limitu. Box větší než tisková podložka se označí a nejde exportovat.',
    'field.gap':'Mezera',
    'field.outer':'Venkovní odsazení',
    'field.outer.hint':'0 = boxy sahají až ke stěnám šuplíku.',

    // Texty pod malým „i" u každého nastavení.
    'tip.dw':'Vnitřní šířka šuplíku, změřená od stěny ke stěně - ne šířka čela.',
    'tip.dd':'Vnitřní hloubka šuplíku, od přední stěny k zadní.',
    'tip.dh':'Jak vysoké boxy budou. Drž se pod výškou otvoru, jinak šuplík nezavřeš.',
    'tip.wall':'Tloušťka stěn boxu. Dva milimetry jsou pevný tisk z PLA i PETG; víc už jen stojí plast a čas.',
    'tip.bottom':'Tloušťka dna. Stejná úvaha jako u stěny - silnější jen když do boxu přijde něco těžkého.',
    'tip.radius':'Jak moc jsou rohy zaoblené. Zaoblený roh se líp tiskne a snáz se z něj vysype obsah.',
    'tip.gap':'Mezera MEZI sousedními boxy, aby o sebe nedřely. Každý box z ní ubere polovinu.',
    'tip.outer':'Volný okraj dokola celého šuplíku. Nastav 1 mm a šuplík 250 mm se vyplní, jako by měl 248 - hotová sada do něj zapadne místo aby se tam musela mačkat.',
    'tip.printw':'Šířka tiskové plochy. Box širší než tohle se označí červeně a nepůjde exportovat. 0 = bez limitu.',
    'tip.printd':'Hloubka tiskové plochy. Otočení je povolené, takže se box vejde, když se vejde aspoň jedním směrem. 0 = bez limitu.',
    'tip.cols':'Na kolik sloupců je šuplík rozdělený. Mřížka je rastr, do kterého boxy zapadají; velikost buňky z ní vychází.',
    'tip.rows':'Na kolik řádků je šuplík rozdělený.',
    'tip.minw':'Nejužší box, který generátor smí udělat, v buňkách. Při 100 % zaplnění použije na zbylé díry i menší.',
    'tip.maxw':'Nejširší box, který generátor smí udělat, v buňkách.',
    'tip.mind':'Nejmělčí box, který generátor smí udělat, v buňkách.',
    'tip.maxd':'Nejhlubší box, který generátor smí udělat, v buňkách.',
    'tip.fill':'Kolik plochy šuplíku vyplnit. Při 100 % nezůstane prázdná buňka.',
    'tip.variation':'Povaha výsledku: vyvážené, míchané, spíš velké nebo spíš malé boxy.',
    'field.columns':'Sloupce',
    'field.rows':'Řádky',
    'field.wcells':'Šířka (buňky)',
    'field.dcells':'Hloubka (buňky)',
    'field.minw':'Min. šířka (buňky)',
    'field.maxw':'Max. šířka (buňky)',
    'field.mind':'Min. hloubka (buňky)',
    'field.maxd':'Max. hloubka (buňky)',
    'field.fill':'Míra zaplnění',
    'field.variation':'Variabilita',
    'field.freemode':'FREE MODE',
    'field.freemode.hint':'Část boxů vyjde jako tvary L a T.',

    'var.balanced':'Vyvážené',
    'var.mixed':'Smíšené',
    'var.large':'Velké boxy',
    'var.small':'Malé boxy',

    'random.note':'Vygeneruje nové rozvržení, které se vejde do aktuální mřížky.',
    'random.undersized':'{0} mezer bylo menších než zadané minimum.',
    'step.hint':'Se Shiftem jemný krok',
    'acct.credits':'Účet a kredity',
    'acct.admin':'Administrace',
    'acct.signout':'Odhlásit se',
    'acct.signin':'Přihlásit se',
    'aria.language':'Jazyk',
    'aria.units':'Zobrazené jednotky',
    'locked.title':'S účtem navíc',
    'locked.body':'Generování náhodných rozvržení s vlastní minimální a maximální velikostí boxů, mírou zaplnění a stylem.',
    'foot.register':'Založit účet','foot.account':'Můj účet','foot.cookies':'Cookies',
    'signin.title':'Přihlášení',
    'signin.lede':'Kredity a uplatněné kódy patří k tvému účtu.',
    'signin.email':'E-mail',
    'signin.password':'Heslo',
    'signin.submit':'Přihlásit se',
    'signin.cancel':'Zrušit',
    'signin.full':'Otevřít celou stránku',
    'signin.forgot':'Zapomenuté heslo?',
    'signin.missing':'Vyplň obě pole.',
    'signin.failed':'Přihlášení se nezdařilo. Zkontroluj adresu a heslo.',
    'foot.units':'Všechny rozměry jsou v {0}.',
    'unit.mm.long':'milimetrech','unit.cm.long':'centimetrech','unit.in.long':'palcích',
    'aria.decrease.dw':'Zmenšit šířku',
    'aria.increase.dw':'Zvětšit šířku',
    'aria.decrease.dd':'Zmenšit hloubku',
    'aria.increase.dd':'Zvětšit hloubku',
    'aria.decrease.dh':'Zmenšit výšku',
    'aria.increase.dh':'Zvětšit výšku',
    'seed':'Seed',

    'stat.boxes':'Boxy',
    'stat.used':'Využité buňky',
    'stat.free':'Volné buňky',
    'stat.status':'Stav',
    'status.ready':'PŘIPRAVENO',
    'status.invalid':'NEPLATNÉ',
    'err.tooshort':'Box musí být vysoký aspoň {0}: dno má {1} a nad ním musí zůstat aspoň 1 mm prostoru.',

    'howto.text':'Box přesuneš tažením. Velikost změníš tažením kterékoli hrany nebo rohu. Boxy zapadají do mřížky a nemohou se překrývat. Klikni na box pro výběr a pak použij červené tlačítko uvnitř pro jeho odstranění.',

    'box.remove':'Smazat box',
    'box.clone':'Duplikovat box',
    'box.grow':'Roztáhnout na vedlejší buňku','box.shrink':'Zmenšit o jednu buňku',
    'box.addcell':'Přidat tohle políčko','box.rmcell':'Odebrat tohle políčko',
    'box.addwall':'Přidat příčku na tuhle hranici','box.addmidwall':'Rozdělit box přesně na polovinu','box.rmwall':'Odebrat tuhle příčku',
    'box.deselect':'Zrušit výběr',
    'err.wallfloats':'Příčka nesmí končit v prázdnu. Doveď ji ke stěně boxu nebo k jiné příčce.',
    'walls.disable.title':'Vypnout režim příček?',
    'walls.disable.body':'Vypnutím režimu příček se smažou všechny nastavené příčky. Tuto akci nelze vrátit.',
    'walls.disable.ok':'Smazat příčky',
    'walls.mode.label':'Režim úprav boxu',
    'walls.mode.walls':'Stěny',
    'walls.grid.label':'Jemnost mřížky příček',
    'walls.grid.coarse':'1 : 1 · základní',
    'walls.grid.fine':'2 : 1 · jemná',
    'walls.grid.freeHint':'Jemnost příček lze změnit po přepnutí na Stěny.',
    'walls.grid.note':'FREE MODE je dostupný jen při vypnutém režimu příček.',
    'walls.shapesOnlyOff':'Před úpravou tvaru ve FREE MODE nejdřív vypni režim příček.',
    'walls.mode.free':'FREE MODE',
    'walls.mode.switchTitle':'Přepnout režim úprav?',
    'walls.mode.toFree':'Free mode smaže aktuálně nakreslené příčky. Boxy samotné zůstanou na místě.',
    'walls.mode.toWalls':'Režim stěn nahradí FREE MODE kreslením příček. Tvar z FREE MODE je před zapnutím stěn potřeba upravit zpět.',
    'walls.mode.toWallsPoly':'Režim stěn převede každý tvar z FREE MODE na navazující obdélníkové boxy. Žádné políčko se neztratí; tvary vrátíš přes Zpět.',
    'walls.mode.switchOk':'Přepnout režim',
    'walls.mode.converted':'Free tvary: {0}; po převodu vzniklo obdélníkových boxů: {1}.',
    'walls.grid.switchTitle':'Přepnout jemnost mřížky příček?',
    'walls.grid.toFine':'Stejný layout se bude upravovat na jemnější mřížce příček 2:1.',
    'walls.grid.toCoarse':'Částečné příčky 2:1 se smažou. Na celou příčku 1:1 se převedou jen dvě navazující poloviny.',
    'walls.grid.switchOk':'Přepnout mřížku',
    'walls.grid.coarsened':'Jemné úseky byly převedeny na {0} celých příček; neúplné úseky byly odstraněny.',
    'box.cellcount':'{0} políček','err.polyhole':'Tvar by uzavřel díru - vyplň ji, nebo pokračuj jinudy.',

    'spend.title':'Volný export je vyčerpaný',
    'spend.body':'Tento export odečte {0} kredit(y). Máš {1} a zůstane ti {2}.',
    'spend.ok':'Použít kredit',
    'spend.cancel':'Zrušit',

    'layout.clearTitle':'Smazat layout?',
    'layout.clearBody':'Odstraní všechny boxy ze zásuvky. Tuhle akci nelze vrátit.',
    'layout.clearOk':'Smazat layout',
    'layout.fillTitle':'Nahradit aktuální layout?',
    'layout.fillBody':'Vyplnění boxy 1×1 nejdřív smaže aktuální layout. Tuhle akci nelze vrátit.',
    'layout.fillOk':'Smazat a vyplnit',

    'err.fixlayout':'Před exportem oprav rozvržení.',
    'err.nobox':'Není co exportovat.',
    'err.oversize':'Některé boxy jsou větší než max. tisková plocha. Zmenši je nebo zvyš limit.',
    'hint.printlimit':'Dosažena max. tisková plocha - box už nejde zvětšit. Zvyš limit, nebo přidej řádek/sloupec mřížky.',
    'err.toomany':'Příliš mnoho boxů na jeden export. Použij Opravit, nebo jich ubyď.',
    'err.colnotempty':'V tom sloupci jsou boxy - nejdřív ho vyprázdni, pak půjde odebrat.',
    'err.rownotempty':'V tom řádku jsou boxy - nejdřív ho vyprázdni, pak půjde odebrat.',
    'err.overflow':'Některé boxy jsou mimo mřížku - přesuň je nebo zmenši, aby se vešly.',
    'err.failed':'Export selhal.',
    'err.http':'Export selhal ({0}).',

    'quota.free':'Volný export je k dispozici.',
    'quota.freeNow':'Export je teď zdarma',
    'quota.freeEvery':'Jeden volný export za {0}.',
    'quota.freeLeft':'Zdarma ještě {0}×, pak jeden za {1}.',
    'quota.freeCount':'Zbývá {0} z {1} volných exportů',
    'quota.exhausted':'Volné exporty vyčerpané',
    'quota.allowance':'{0}× zdarma za {1}.',
    'quota.nextBack':'Další volný export se vrátí za {0}.',
    'quota.resetIn':'Jeden se vrátí za {0}.',
    'quota.resetAll':'Kvóta se obnoví za {0}.',
    'quota.costs':'Tento export stojí {0} kredit(y)',
    'quota.nextFree':'Zdarma zase za {0}.',
    'quota.everyCosts':'Každý export stojí kredity.',
    'quota.needCredits':'K exportu potřebuješ kredity',
    'quota.waitFor':'Zdarma zase za {0}',
    'quota.balance':'Zůstatek: {0} kreditů',
    'quota.credit':'Další volný export za {0}. Export teď stojí {1} kredit(y).',
    'quota.blocked':'Další volný export za {0}.',
    'quota.login':'Pro export se přihlas',
    'quota.nocredits':'Nemáš žádné kredity.',
    'quota.signin':'Přihlásit se',
    'quota.getcredits':'Získat kredity',
    'quota.charged':'Odečteno {0} kredit(ů). Zbývá {1}.',
    'quota.freeShort':'{0}/{1} zdarma',
    'quota.freeExports':'Volné exporty','quota.remaining':'zbývá {0} z {1}',
    'quota.upgrade':'Upgrade',
    'nav.home':'Domů','nav.generate':'Generovat','nav.edit':'Upravit layout','nav.boxdetails':'Detaily boxu','nav.export':'Export','nav.share':'Sdílet','nav.load':'Načíst projekt','nav.howto':'Návod','nav.about':'O aplikaci','nav.collapse':'Sbalit menu','nav.expand':'Rozbalit menu','nav.dims':'Rozměry zásuvky','nav.print':'Tisková plocha','nav.grid':'Mřížka','nav.layout':'Generátor','nav.inspector':'Inspektor','nav.account':'Účet','nav.admin':'Administrace','nav.signout':'Odhlásit se','nav.signin':'Přihlásit se','nav.prefs':'Předvolby','nav.quickSettings':'Rychlé nastavení','pref.units':'Jednotky','pref.language':'Jazyk','pref.theme':'Motiv','onboard.title':'Vítej ve studiu','onboard.lede':'Vyber, jak má studio vypadat. Vše lze později změnit v Předvolbách.','onboard.language':'Jazyk','onboard.units':'Jednotky','onboard.theme':'Motiv','onboard.start':'Začít navrhovat','export.json.label':'Uložit projekt','export.json.desc':'Stáhne layout jako soubor, který lze později načíst.','export.json.locked':'Přihlas se pro uložení a sdílení projektu.','lock.title':'Dostupné po přihlášení','lock.print':'Nastav plochu tiskárny, ať se box, který se nevejde, označí ještě před exportem.','lock.layout':'Automaticky vyplní zásuvku boxy podle tvých pravidel - min/max velikost, zaplnění a styl.','lock.inspector':'Živý přehled: počet boxů, využité a volné buňky, platnost a Opravit jedním klikem.','lock.load':'Otevře uložený soubor projektu a naváže na návrh tam, kde jsi skončil.','lock.walls':'Rozdělí box na přihrádky pomocí vnitřních příček. Jejich pozici nastavíš přímo v mřížce boxu a příčky se tisknou jako součást boxu.',
    'ct.drawer':'Rozměry šuplíku','ct.selected':'Vybraný box','credits.title':'Kredity a exporty','credits.balance':'Kredity','credits.free':'Volné exporty','credits.reset':'Obnova limitu za','credits.cost':'Cena exportu','credits.note':'Nejdřív se čerpají volné exporty, kredity až po jejich vyčerpání.','credits.manage':'Spravovat účet a kredity','credits.unlimited':'Neomezeně','credits.cr':'kreditů',
    'status.dims':'Rozměry zásuvky','status.boxes':'Celkem boxů','status.cells':'Využité buňky','status.saved':'Poslední uložení','status.now':'Právě teď','status.minsAgo':'před {0} min','status.hoursAgo':'před {0} h',
    'tool.select':'Výběr a úpravy','tool.pan':'Posun plátna','tool.undo':'Zpět (Ctrl+Z)','tool.redo':'Vpřed (Ctrl+Shift+Z)','tool.grid':'Přepnout mřížku','tool.labels':'Přepnout popisky','tool.fit':'Přizpůsobit pohled','tool.zoomout':'Oddálit','tool.zoomin':'Přiblížit','tool.center':'Na střed','share.saved':'Layout uložen jako soubor','share.title':'Sdílet návrh','share.desc':'Vytvoří odkaz bez dat návrhu v URL. Návrh je uložen pod bezpečným klíčem.','share.locked':'Přihlas se pro vytvoření odkazu na návrh.','share.created':'Odkaz na návrh byl zkopírován do schránky','share.popup.title':'Odkaz na návrh','share.popup.copied':'Odkaz byl zkopírován do schránky.','share.loaded':'Sdílený návrh byl načten','share.error':'Odkaz na návrh se nepodařilo vytvořit nebo načíst.','load.bad':'Soubor projektu se nepodařilo načíst','share.popup.copy':'Kopírovat odkaz','share.popup.copyHint':'Zkopíruj ho tlačítkem níž.','share.preview.title':'Sdílený návrh','share.preview.desc':'Někdo ti poslal tenhle návrh. Nejdřív si ho prohlédni - do studia se nedostane, dokud neřekneš.','share.preview.summary':'Šuplík {0} · {1} boxů · mřížka {2} × {3}','share.preview.warn':'Převzetím se nahradí návrh, který máš teď otevřený, a vrátit to nejde.','share.preview.empty':'Tvůj šuplík je prázdný, takže o nic nepřijdeš.','share.preview.now':'Teď','share.preview.after':'Po převzetí','share.preview.close':'Zavřít','share.preview.take':'Převzít návrh','share.preview.taken':'Sdílený návrh převzat',
    'help.intro':'Navrhni si vlastní organizér do zásuvky a exportuj ho jako 3MF nebo STL připravené k tisku. Vše se přepočítává živě.',
    'help.dims.t':'Rozměry šuplíku','help.dims.b':'Změř šuplík zevnitř, ne přední čelo, a zadej šířku, hloubku a výšku. Pod tím je tloušťka stěny, tloušťka dna, zaoblení rohů a mezera mezi boxy - kvůli mezeře je hotový box o kousek menší než buňka, ve které stojí, a proto do šuplíku zapadne místo aby se vzpříčil.',
    'help.grid.t':'Mřížka','help.grid.b':'Sloupce a řádky jsou rastr, do kterého boxy zapadají. Při změně zůstane, co už je nakreslené. Čtverečky + a - po okrajích kresby přidají nebo uberou celý sloupec či řádek na té straně; sloupec, ve kterém ještě stojí box, odebrat nejde.',
    'help.edit.t':'Kreslení a úpravy','help.edit.b':'Klikni na prázdnou buňku a vznikne v ní box 1x1. Box přesuneš tažením, velikost změníš tažením za hranu nebo roh - boxy zapadají do mřížky a nikdy se nepřekrývají. Vybraný box má vedle sebe bublinu s rozměrem, tlačítkem duplikovat a košem a ovládání pro změnu jeho rozměrů.',
    'help.generate.t':'Generátor','help.generate.b':'Vyplní celý šuplík za tebe. Zvol nejmenší a největší box v buňkách, míru zaplnění a styl, pak Vygenerovat; každý běh vyjde jinak. Při 100 % se zbylé díry doplní i boxy menšími, než je nastavené minimum - do díry o jedné buňce se nic jiného nevejde.',
    'help.tools.t':'Nástroje pod kresbou','help.tools.b':'Šipka vybírá a upravuje, ruka posouvá plátno a pustí vybraný box. Dál koš, který smaže celý layout, zpět a vpřed, přiblížení, na střed a zobrazit celý návrh.',
    'help.print.t':'Tisková plocha a oprava','help.print.b':'Zadej plochu své tiskárny a studio tě nenechá udělat box, který se na ni nevejde: zčervená, export se zablokuje a ikona Přehled dostane červenou značku. Její tlačítko Opravit přidává sloupce a řádky, dokud se vše nevejde, a rozměrů šuplíku se nedotkne.',
    'help.export.t':'Export','help.export.b':'3MF zachová každý box jako samostatný objekt, což mají Bambu Studio i OrcaSlicer nejraději. STL je vloží do jednoho souboru jako samostatné skořepiny. Model se vždy staví ve skutečné velikosti v milimetrech, ať editor ukazuje jakoukoli jednotku.',
    'help.project.t':'Uložit a načíst projekt','help.project.b':'Uložit projekt zapíše celý návrh do malého souboru - šuplík, mřížku, boxy i nastavení. Načíst projekt ho zase otevře, takže můžeš pokračovat jindy nebo návrh někomu poslat.',
    'help.pro.t':'Co přidá přihlášený účet',
    'help.pro.1':'Generátor, kontrolu tiskové plochy a Přehled s živými čísly a opravou na jedno kliknutí.',
    'help.pro.2':'Zpět a vpřed přes posledních pět kroků, v liště pod kresbou (Ctrl+Z, Ctrl+Shift+Z).',
    'help.pro.3':'Úchyty + a - pro zvětšení a zmenšení mřížky.',
    'help.pro.5':'Uložení a načtení projektu jako souboru.',
    'help.pro.6':'Šuplík, mřížka, tisková plocha i rozdělaný layout zůstanou v profilu a vrátí se příště - ikona účtu zezelená, když je vše uloženo.',
    'help.pro.7':'Víc volných exportů než má host, k tomu kredity, kódy a časové balíčky.',
    'foot.terms':'Podmínky použití',
    'confirm.dontask':'Příště nepřipomínat',
    'news.title':'Co je nového','news.ok':'Rozumím',
    'pre.title':'Než to pošleš do tiskárny',
    'pre.body':'Soubor se staví přesně z čísel, která jsi zadal, takže se vyplatí ho nejdřív otevřít ve sliceru a projít: sedí rozměr šuplíku s tím, co jsi naměřil zevnitř, vejdou se všechny boxy na podložku a jsou stěny i příčky tam, kde jsi je chtěl? Zkušební tisk jednoho malého boxu ušetří spoustu plastu, kdyby něco nesedělo.',
    'pre.ok':'Rozumím, stáhnout',
    'help.pro.8':'Příčky v boxu, přes tlačítko se stěnou pod kresbou.',
    'help.walls.t':'Příčky v boxu',
    'help.walls.b':'Tlačítko se stěnou pod kresbou udělá z boxu něco, do čeho se kreslí stěny: na každé čáře mezi dvěma jeho buňkami se nabídne plus a kliknutím tam příčka vznikne. Je silná jednu stěnu, přesně tu z Konstrukce, a tiskne se jako součást boxu - takže box rozdělený na čtyři je jeden kus, ne čtyři. Každý konec musí dojít k vnější stěně nebo k jiné příčce; tu, co zůstane viset v prázdnu, ohlásí Přehled a tlačítko Opravit ji odebere. Příčky mají uvnitř ostré rohy, zatímco vnější rohy boxu mohou zůstat zaoblené.',
    'tool.walls':'Příčky v boxu','aria.tools':'Nástroje','aria.history':'Zpět a vpřed','aria.zoom':'Zoom',
    'help.account.t':'Účet a kredity','help.account.b':'Každý export nejdřív ubere z volných exportů; když dojdou, stojí kredit. Pokud je zapnutý denní příděl kreditů, jeho výše i strop jsou v limitech nahoře v této nápovědě. Počítadlo v Exportu říká, co zbývá, a kredity se dobíjejí na stránce účtu nebo kódem. Limity, které platí právě teď, jsou nahoře v této nápovědě.'
  }
};

let LANG=(window.H3D_LANG==='cs')?'cs':'en';

/** Translate a key, optionally substituting {0}, {1}, ... */
function t(key,...args){
  const table=I18N[LANG]||I18N.en;
  let s=(key in table)?table[key]:(I18N.en[key]!==undefined?I18N.en[key]:key);
  args.forEach((v,i)=>{s=s.split('{'+i+'}').join(v)});
  return s;
}


const $=id=>document.getElementById(id), svg=$('svg');
let boxes=[],selected=null,nextId=1,drag=null;

/* ============================================================
   Undo / redo: the last five steps, for signed-in users.
   ============================================================

   Rather than remembering to record a step in each of the twenty-odd
   places that change the layout - generate, clone, delete, sculpt a cell,
   grow the grid, fix, clear, drag - the history watches the result. Every
   one of them ends in draw(), so draw() compares the layout with the one
   it saw last and keeps the previous version when they differ. Nothing can
   forget to be undoable.

   Only the layout is kept: boxes, the grid, and the id counter. Drawer
   size, units and view are settings, not steps, and stepping back should
   not silently resize somebody's drawer.

   Guests are not offered it: their work is not saved anywhere, so a
   history that dies with the tab would promise more than it keeps. */
const HIST={
  limit:5,
  undo:[],
  redo:[],
  last:null,
  /** Set while history itself is restoring, so the redraw is not a step. */
  busy:false,
  on:!!(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn),
};

function historyState(){
  return JSON.stringify({
    c:+$('cols').value, r:+$('rows').value, n:nextId,
    b:boxes.map(b=>({
      id:b.id,x:b.x,y:b.y,w:b.w,h:b.h,
      colorSlot:Number.isInteger(b.colorSlot)?b.colorSlot:null,
      cells:isPoly(b)?cellsOf(b).map(c=>({x:c.x,y:c.y})):null,
      walls:Array.isArray(b.walls)?b.walls.slice():null,
      midWalls:Array.isArray(b.midWalls)?b.midWalls.slice():null,
      halfWalls:Array.isArray(b.halfWalls)?b.halfWalls.slice():null,
    })),
  });
}

function historyButtons(){
  if($('undoBtn')) $('undoBtn').disabled=!HIST.undo.length;
  if($('redoBtn')) $('redoBtn').disabled=!HIST.redo.length;
}

/** Called at the end of every draw. Records the step that just happened. */
function historyTick(){
  if(!HIST.on)return;
  // Mid-drag every pointer move redraws; the step is the finished move, not
  // each pixel of it. The pointerup redraw is the one that counts.
  if(drag)return;
  const now=historyState();
  if(HIST.last===null){HIST.last=now;historyButtons();return;}
  if(now===HIST.last)return;
  if(!HIST.busy){
    HIST.undo.push(HIST.last);
    if(HIST.undo.length>HIST.limit)HIST.undo.shift();
    // A fresh change is a new branch: what was undone cannot be redone.
    HIST.redo.length=0;
  }
  HIST.last=now;
  historyButtons();
}

/** Make right now the beginning: what loaded at startup is not a step. */
function historyReset(){
  HIST.undo.length=0;
  HIST.redo.length=0;
  HIST.last=HIST.on?historyState():null;
  historyButtons();
}

function historyApply(json){
  const s=JSON.parse(json);
  boxes=s.b.map(b=>{
    const nb={id:b.id,x:b.x,y:b.y,w:b.w,h:b.h};
    if(Number.isInteger(b.colorSlot))nb.colorSlot=b.colorSlot;
    if(b.cells)nb.cells=b.cells.map(c=>({x:c.x,y:c.y}));
    if(b.walls)nb.walls=b.walls.slice();
    if(b.midWalls)nb.midWalls=b.midWalls.slice();
    if(b.halfWalls)nb.halfWalls=b.halfWalls.slice();
    return nb;
  });
  nextId=s.n;
  $('cols').value=s.c;
  $('rows').value=s.r;
  if(selected!==null&&!boxes.some(b=>b.id===selected))selected=null;
  // The grid moved, so everything keyed to it follows: the box size pickers,
  // the slider read-outs and the generator's own column/row choosers.
  if(typeof syncGridUI==='function')syncGridUI();
  draw();
}

function historyStep(back){
  if(!HIST.on)return;
  const from=back?HIST.undo:HIST.redo;
  const to=back?HIST.redo:HIST.undo;
  if(!from.length)return;
  const here=historyState();
  const there=from.pop();
  to.push(here);
  if(to.length>HIST.limit)to.shift();
  HIST.busy=true;
  historyApply(there);
  HIST.busy=false;
  HIST.last=historyState();
  historyButtons();
}

// Server-side ceilings mirrored into the studio so the interface can warn
// before an export the server would only reject. 0 = no limit configured.
const MAXBOXES=(window.H3D_MAXBOXES|0)||0;

/*
 * The largest grid the shop allows, straight from the settings.
 *
 * It used to be the number 15 written into the slider, the ghost handles,
 * the Fix routine and the restore path. Setting the limit to 13 in the
 * panel therefore changed the export rules but not the studio: the slider
 * still went to 15 and produced a layout the server then refused.
 */
const MAXCELLS=Math.max(1,(window.H3D_LIMITS&&+window.H3D_LIMITS.maxCells)||15);
let hasTooMany=false;

// Whether the last draw found a box bigger than the print bed. A single value
// means a square bed; zero on both axes means no limit. Rotation on the bed is
// allowed, so a box fits if it does either way round.
let hasOversize=false;
function overMax(pW,pD){
  // Print-area limits are stored canonically in millimetres (like every other
  // dimension), so the check is unit-independent even when the field shows cm/in.
  const a=($('maxPrintW')?getMM('maxPrintW'):0)||0;
  const b=($('maxPrintD')?getMM('maxPrintD'):0)||0;
  const mw=a>0?a:b, md=b>0?b:a;
  if(mw<=0||md<=0)return false;
  const e=1e-6;
  return !((pW<=mw+e&&pD<=md+e)||(pW<=md+e&&pD<=mw+e));
}

/** Real millimetre size of a w×h box in the current drawer, and whether it
 *  would break the print-area limit. Used to refuse an oversized resize up
 *  front, so a box like 309×125 never gets created in the first place. */
function physSize(w,h){
  const gg=grid();
  const drawerW=getMM('dw'),drawerD=getMM('dd'),gapMM=getMM('gap');
  // The outer inset comes off the usable area before the cells are cut from
  // it - exactly as in layout() and in the exporter. Without it the labels
  // and the print-area check reported the old, larger cell.
  const o=outerMM(Math.max(1,drawerW),Math.max(1,drawerD));
  const cellW=(drawerW-2*o-(gg.c-1)*gapMM)/gg.c, cellD=(drawerD-2*o-(gg.r-1)*gapMM)/gg.r;
  return {pW:w*cellW+(w-1)*gapMM, pD:h*cellD+(h-1)*gapMM};
}
function boxTooBig(w,h){ const s=physSize(w,h); return overMax(s.pW,s.pD); }

function options(sel,max){sel.innerHTML='';for(let i=1;i<=max;i++){let o=document.createElement('option');o.value=i;o.textContent=i;sel.appendChild(o)}}
function grid(){return {c:+$('cols').value,r:+$('rows').value}}
$('cols').value=4;$('rows').value=4;
if(typeof setReadout==='function'){ setReadout('colsVal',4); setReadout('rowsVal',4); }
if($('bw')){options($('bw'),4);options($('bd'),4);}

/* ---- Free-shape (polyomino) boxes ----
 * A signed-in user can grow a box cell by cell into an L, a T or any other
 * edge-connected shape. Such a box carries b.cells (absolute grid cells);
 * x/y/w/h stay synced to the bounding box so labels, print-size checks and
 * the gradient keep working unchanged. */
function isPoly(b){
 return Array.isArray(b.cells)&&b.cells.length>0&&b.cells.length!==b.w*b.h;
}
function cellsOf(b){
 if(Array.isArray(b.cells)&&b.cells.length){
   return b.cells.map(c=>({x:c.x|0,y:c.y|0}));
 }
 const out=[];
 for(let y=b.y;y<b.y+b.h;y++)for(let x=b.x;x<b.x+b.w;x++)out.push({x,y});
 return out;
}
function syncPolyBounds(b){
 if(!Array.isArray(b.cells)||!b.cells.length){ delete b.cells;sanitizeWalls(b);return; }
 const unique=new Map();
 b.cells.forEach(c=>{
   const x=c&&Number.isFinite(+c.x)?Math.trunc(+c.x):NaN;
   const y=c&&Number.isFinite(+c.y)?Math.trunc(+c.y):NaN;
   if(Number.isFinite(x)&&Number.isFinite(y))unique.set(x+','+y,{x,y});
 });
 const cells=[...unique.values()];
 if(!cells.length){delete b.cells;sanitizeWalls(b);return;}
 const xs=cells.map(c=>c.x),ys=cells.map(c=>c.y);
 b.x=Math.min(...xs);b.y=Math.min(...ys);
 b.w=Math.max(...xs)-b.x+1;b.h=Math.max(...ys)-b.y+1;
 // A full rectangle uses the ordinary (and simpler) rectangle editor again.
 if(cells.length===b.w*b.h)delete b.cells;
 else b.cells=cells;
 sanitizeWalls(b);
}
/** Edge-connected components of a cell list, for splitting a cut shape. */
function cellComponents(cells){
 const key=c=>c.x+','+c.y;
 const pool=new Map(cells.map(c=>[key(c),c]));
 const out=[];
 while(pool.size){
   const first=pool.values().next().value;
   pool.delete(key(first));
   const comp=[],st=[first];
   while(st.length){
     const c=st.pop();
     comp.push(c);
     [[1,0],[-1,0],[0,1],[0,-1]].forEach(([dx,dy])=>{
       const k=(c.x+dx)+','+(c.y+dy);
       if(pool.has(k)){st.push(pool.get(k));pool.delete(k);}
     });
   }
   out.push(comp);
 }
 return out;
}

/**
 * Partition a free L/T shape into non-overlapping rectangles.  This keeps
 * every occupied cell but gives Wall mode only normal rectangular boxes to
 * work with.  Equal horizontal runs are joined vertically where possible,
 * so the result is compact instead of one box per cell.
 */
function rectanglesFromCells(cells){
 const rows=new Map();
 cells.forEach(c=>{
   if(!rows.has(c.y))rows.set(c.y,[]);
   rows.get(c.y).push(c.x);
 });
 const rects=[];
 let active=new Map();
 [...rows.keys()].sort((a,b)=>a-b).forEach(y=>{
   const xs=[...new Set(rows.get(y))].sort((a,b)=>a-b);
   const runs=[];
   for(let i=0;i<xs.length;){
     const x=xs[i],start=x;let end=x;i++;
     while(i<xs.length&&xs[i]===end+1){end=xs[i];i++;}
     runs.push({x:start,w:end-start+1});
   }
   const next=new Map();
   runs.forEach(run=>{
     const key=run.x+','+run.w;
     const previous=active.get(key);
     if(previous&&previous.y+previous.h===y){
       previous.h++;
       next.set(key,previous);
       return;
     }
     const rect={x:run.x,y,w:run.w,h:1};
     rects.push(rect);next.set(key,rect);
   });
   active=next;
 });
 return rects;
}

/** Convert all L/T/free-shape boxes to an exact rectangular partition. */
function convertFreeBoxesToRectangles(){
 let converted=0,created=0;
 const next=[];
 boxes.forEach(b=>{
   if(!isPoly(b)){next.push(b);return;}
   const rects=rectanglesFromCells(cellsOf(b));
   const base={...b};
   delete base.cells;delete base.walls;delete base.midWalls;delete base.halfWalls;
   rects.forEach((r,index)=>{
     next.push({...base,id:index===0?b.id:nextId++,x:r.x,y:r.y,w:r.w,h:r.h});
   });
   converted++;created+=rects.length;
 });
 boxes=next;
 if(selected!==null&&!boxes.some(b=>b.id===selected))selected=null;
 return {converted,created};
}

// Cells hold together along shared edges. Touching at a bare corner is not
// a join - the two halves would hang on nothing - so a shape stays walkable
// edge to edge. Closing into a ring is fine; the hole is allowed.
function cellsConnected(cells){
 if(!cells.length)return false;
 const key=c=>c.x+','+c.y,set=new Set(cells.map(key));
 const seen=new Set([key(cells[0])]),st=[cells[0]];
 while(st.length){
   const c=st.pop();
   [[1,0],[-1,0],[0,1],[0,-1]].forEach(([dx,dy])=>{
     const k=(c.x+dx)+','+(c.y+dy);
     if(set.has(k)&&!seen.has(k)){seen.add(k);st.push({x:c.x+dx,y:c.y+dy})}
   });
 }
 return seen.size===cells.length;
}
function cellsHaveHole(cells){
 // Flood the emptiness around the shape; an empty cell the flood cannot
 // reach is sealed inside - unprintable and refused by the server too.
 const xs=cells.map(c=>c.x),ys=cells.map(c=>c.y);
 const x0=Math.min(...xs)-1,y0=Math.min(...ys)-1,x1=Math.max(...xs)+1,y1=Math.max(...ys)+1;
 const set=new Set(cells.map(c=>c.x+','+c.y));
 const seen=new Set([x0+','+y0]),st=[[x0,y0]];
 while(st.length){
   const [px,py]=st.pop();
   [[1,0],[-1,0],[0,1],[0,-1]].forEach(([dx,dy])=>{
     const nx=px+dx,ny=py+dy;
     if(nx<x0||ny<y0||nx>x1||ny>y1)return;
     const k=nx+','+ny;
     if(set.has(k)||seen.has(k))return;
     seen.add(k);st.push([nx,ny]);
   });
 }
 return seen.size!==(x1-x0+1)*(y1-y0+1)-cells.length;
}

/* ---- Dividers inside a box ----
 * A signed-in user can split a box with internal walls. A divider lives on
 * the boundary between two of the box's OWN cells and is stored as that
 * boundary in absolute grid coordinates: 'v:3,2' is the line between cells
 * (3,2) and (4,2), 'h:3,2' the line between (3,2) and (3,3).
 *
 * Absolute on purpose. A box moves, grows, loses a cell and turns from a
 * rectangle into an L constantly, and a key that means the same line before
 * and after needs no remapping at all - the box-local index arrays this
 * replaces had to be rebuilt on every one of those moves, and a wall slid
 * into the next column whenever that went wrong.
 */
// Authenticated accounts get project protection, history and server saves.
// E-mail verification is a separate gate for advanced Studio features.
function signedIn(){ return !!(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn); }
function verifiedIn(){
  return !!(window.H3D_QUOTA&&window.H3D_QUOTA.verified);
}

/*
 * Wall mode: the button in the tool strip that turns the box into something
 * you draw walls in.
 *
 * Dividers are compatible with rounded outer corners. The outer contour
 * keeps the configured radius; divider corners themselves remain square.
 */
// A signed-in user gets the divider handles in the normal editor straight
// away. Visitors still see the locked entry point, and saved preferences may
// subsequently turn the mode off again.
let WALLMODE=verifiedIn();
let WALLSTASH=null;
// 1 = original cell grid, 2 = every cell split into four editable parts.
let WALLGRID=1;

function dividersOn(){
 return verifiedIn();
}
function wallModeOn(){ return signedIn()&&WALLMODE&&dividersOn(); }
function shapeEditOn(){ return signedIn()&&!wallModeOn(); }

async function setWallMode(on,keepRadius,confirmLoss=true){
 on=!!on&&signedIn();
 const convertingFree=on&&typeof boxes!=='undefined'&&boxes.some(isPoly);
 if(on===WALLMODE&&!convertingFree)return true;

 // Mode changes always get an explicit confirmation. Leaving wall mode also
 // removes dividers, while entering it changes the editor that owns a box.
 if(confirmLoss){
   const ok=await confirmAction(
     t('walls.mode.switchTitle'),
     t(convertingFree?'walls.mode.toWallsPoly':(on?'walls.mode.toWalls':'walls.mode.toFree')),
     t('walls.mode.switchOk')
   );
   if(!ok)return false;
 }

 if(convertingFree){
   const result=convertFreeBoxesToRectangles();
   if(typeof showExportNote==='function')showExportNote(t('walls.mode.converted',result.converted,result.created));
 }

 if(!on){
   // draw() records the previous layout, so the deletion remains recoverable
   // with Undo for signed-in users.
   boxes.forEach(b=>{ if(Array.isArray(b.walls)) delete b.walls; if(Array.isArray(b.midWalls)) delete b.midWalls; if(Array.isArray(b.halfWalls)) delete b.halfWalls; });
 }

  WALLMODE=on;

 // Dividers are allowed with rounded outer corners, so wall mode no longer
 // owns or changes the corner-radius setting.
 WALLSTASH=null;
  paintWallMode();
  paintWallGrid();
 draw();
 if(typeof savePrefsDebounced==='function')savePrefsDebounced();
 return true;
}

/** The radius field, its slider, its preview and the drawing, in one move. */
function setRadiusMM(mm){
 const el=$('radius');
 if(!el)return;
 setMM('radius',mm);
 el.value=formatUnit(getMM('radius'));
 const sl=$('radiusSlider');
 if(sl){ sl.value=getMM('radius'); if(typeof updateRange==='function')updateRange('radiusSlider'); }
 if(typeof updateRadiusPreview==='function')updateRadiusPreview();
}

/** The FREE MODE switch belongs to the shape editor, so it goes with it. */
function paintFreeModeSwitch(){
 const row=document.querySelector('.random-free');
 if(!row)return;
 const off=wallModeOn();
 row.hidden=off;
 const box=$('randomFree');
 if(box){ box.disabled=off; if(off) box.checked=false; }
}

function paintWallMode(){
 const btn=$('wallModeBtn'),freeBtn=$('freeModeBtn');
 const guest=!verifiedIn();
 if(btn){
   btn.hidden=false;
   btn.classList.toggle('active',!guest&&WALLMODE);
   btn.classList.toggle('guest-lock',guest);
   btn.setAttribute('aria-pressed',!guest&&WALLMODE?'true':'false');
   btn.disabled=!guest&&WALLMODE;
   btn.setAttribute('aria-disabled',(guest||WALLMODE)?'true':'false');
 }
 if(freeBtn){
   freeBtn.hidden=false;
   freeBtn.classList.toggle('active',!guest&&!WALLMODE);
   freeBtn.classList.toggle('guest-lock',guest);
   freeBtn.setAttribute('aria-pressed',!guest&&!WALLMODE?'true':'false');
   freeBtn.disabled=!guest&&!WALLMODE;
   freeBtn.setAttribute('aria-disabled',(guest||!WALLMODE)?'true':'false');
 }
 const walls=$('wallEditWallsBtn'),free=$('wallEditFreeBtn');
 if(walls){
   walls.disabled=guest||WALLMODE;walls.classList.toggle('active',!guest&&WALLMODE);
   walls.setAttribute('aria-pressed',!guest&&WALLMODE?'true':'false');
   walls.setAttribute('aria-disabled',(guest||WALLMODE)?'true':'false');
 }
 if(free){
   free.disabled=guest||!WALLMODE;free.classList.toggle('active',!guest&&!WALLMODE);
   free.setAttribute('aria-pressed',!guest&&!WALLMODE?'true':'false');
   free.setAttribute('aria-disabled',(guest||!WALLMODE)?'true':'false');
 }
}
function paintWallGrid(){
 const buttons=[...document.querySelectorAll('[data-wall-grid]')];
 const field=document.querySelector('.wall-grid-field');
 const state=$('wallGridState');
 const toolbar=$('ctWallGrid');
 if(!buttons.length){if(field)field.hidden=true;if(toolbar)toolbar.hidden=true;paintWallMode();return;}
  const ok=verifiedIn();
 if(field)field.hidden=!ok||!WALLMODE;
 if(toolbar)toolbar.hidden=!ok||!WALLMODE;
 if(state)state.hidden=true;
  buttons.forEach(btn=>{
    const value=+btn.dataset.wallGrid;
    const active=ok&&WALLMODE&&value===WALLGRID;
   btn.disabled=!ok||!WALLMODE;
   btn.classList.toggle('active',active);
   btn.classList.remove('coming-soon');
   btn.setAttribute('aria-pressed',active?'true':'false');
   btn.setAttribute('aria-disabled',(!ok||!WALLMODE)?'true':'false');
   btn.removeAttribute('title');
 });
 paintWallMode();
}
async function setWallGrid(next){
 next=next===2?2:1;
 if(!verifiedIn()||!WALLMODE||next===WALLGRID)return false;
 const changingToCoarse=next===1&&WALLGRID===2;
 const ok=await confirmAction(
   t('walls.grid.switchTitle'),
   t(changingToCoarse?'walls.grid.toCoarse':'walls.grid.toFine'),
   t('walls.grid.switchOk')
 );
 if(!ok)return false;
 let converted=0;
 if(changingToCoarse)boxes.forEach(b=>{converted+=collapseFineWalls(b);});
 else boxes.forEach(b=>{expandCoarseWalls(b);});
 WALLGRID=next;
 paintWallGrid();draw();
 if(changingToCoarse&&typeof showExportNote==='function')showExportNote(t('walls.grid.coarsened',converted));
 if(typeof savePrefsDebounced==='function')savePrefsDebounced();
 return true;
}
function normaliseWallGrid(){
 if(WALLGRID===1)boxes.forEach(b=>{collapseFineWalls(b);});
 else boxes.forEach(b=>{expandCoarseWalls(b);});
}
function wallKey(type,x,y){return type+':'+x+','+y}
function wallCells(k){
 const type=k[0];
 const [x,y]=k.slice(2).split(',').map(Number);
 return type==='v'?[{x,y},{x:x+1,y}]:[{x,y},{x,y:y+1}];
}
function boxOwnsWall(b,k){
 const set=new Set(cellsOf(b).map(c=>c.x+','+c.y));
 return wallCells(k).every(c=>set.has(c.x+','+c.y));
}
function hasWalls(b){return Array.isArray(b.walls)&&b.walls.length>0}
// A centred divider is independent of the editing grid. It lets a 1 x 3
// box split into equal compartments rather than only at a cell edge.
function hasMidWalls(b){return Array.isArray(b.midWalls)&&b.midWalls.length>0}
function hasHalfWalls(b){return Array.isArray(b.halfWalls)&&b.halfWalls.length>0}
function hasAnyWalls(b){return hasWalls(b)||hasMidWalls(b)||hasHalfWalls(b)}
/**
 * 2:1 stores every half-grid segment separately. When returning to 1:1, a
 * normal divider exists only where BOTH halves of one original cell edge are
 * present. Everything else is deliberately discarded: keeping it alongside
 * the 1:1 line caused double, visually overlapping walls.
 */
function collapseFineWalls(b){
 if(!hasHalfWalls(b))return 0;
 const fine=new Set(b.halfWalls),normal=new Set(Array.isArray(b.walls)?b.walls:[]);
 const owned=new Set(cellsOf(b).map(c=>c.x+','+c.y));
 const owns=(x,y)=>owned.has(x+','+y);
 let converted=0;
 cellsOf(b).forEach(c=>{
   if(owns(c.x+1,c.y)){
     const hx=(c.x-b.x+1)*2,hy=(c.y-b.y)*2;
     if(fine.has(halfWallKey('v',hx,hy))&&fine.has(halfWallKey('v',hx,hy+1))){
       const key=wallKey('v',c.x,c.y);if(!normal.has(key)){normal.add(key);converted++;}
     }
   }
   if(owns(c.x,c.y+1)){
     const hx=(c.x-b.x)*2,hy=(c.y-b.y+1)*2;
     if(fine.has(halfWallKey('h',hx,hy))&&fine.has(halfWallKey('h',hx+1,hy))){
       const key=wallKey('h',c.x,c.y);if(!normal.has(key)){normal.add(key);converted++;}
     }
   }
 });
 if(normal.size)b.walls=[...normal];
 delete b.halfWalls;
 sanitizeWalls(b);
 return converted;
}
// The opposite conversion prevents a 1:1 divider from being drawn on top of
// a new 2:1 segment at the same location. A coarse wall becomes its two exact
// fine halves, so switching back and forth is lossless while it stays whole.
function expandCoarseWalls(b){
 if(!hasWalls(b))return 0;
 const fine=new Set(Array.isArray(b.halfWalls)?b.halfWalls:[]);
 const owned=new Set(cellsOf(b).map(c=>c.x+','+c.y));
 const owns=(x,y)=>owned.has(x+','+y);
 let converted=0;
 b.walls.forEach(key=>{
   const [type,coords]=key.split(':');
   const [x,y]=coords.split(',').map(Number);
   if(type==='v'&&owns(x,y)&&owns(x+1,y)){
     const hx=(x-b.x+1)*2,hy=(y-b.y)*2;
     fine.add(halfWallKey('v',hx,hy));fine.add(halfWallKey('v',hx,hy+1));converted++;
   }
   if(type==='h'&&owns(x,y)&&owns(x,y+1)){
     const hx=(x-b.x)*2,hy=(y-b.y+1)*2;
     fine.add(halfWallKey('h',hx,hy));fine.add(halfWallKey('h',hx+1,hy));converted++;
   }
 });
 if(fine.size)b.halfWalls=[...fine];
 delete b.walls;
 sanitizeWalls(b);
 return converted;
}
function toggleMidWall(b,type){
 if(!Array.isArray(b.midWalls))b.midWalls=[];
 const key='m:'+type,i=b.midWalls.indexOf(key);
 if(i>=0)b.midWalls.splice(i,1);else b.midWalls.push(key);
 if(!b.midWalls.length)delete b.midWalls;
}
function halfWallKey(type,x,y){return type+':'+x+','+y}
function toggleHalfWall(b,type,x,y){
 if(!Array.isArray(b.halfWalls))b.halfWalls=[];
 const key=halfWallKey(type,x,y),i=b.halfWalls.indexOf(key);
 if(i>=0)b.halfWalls.splice(i,1);else b.halfWalls.push(key);
 if(!b.halfWalls.length)delete b.halfWalls;
}
/** Walls the box no longer owns are gone - it was reshaped under them. */
function sanitizeWalls(b){
 if(Array.isArray(b.walls)){
   b.walls=b.walls.filter(k=>boxOwnsWall(b,k));
   if(!b.walls.length)delete b.walls;
 }
 if(Array.isArray(b.halfWalls)){
   b.halfWalls=b.halfWalls.filter(k=>{
     const m=/^([vh]):(\d+),(\d+)$/.exec(k); if(!m)return false;
     const vertical=m[1]==='v',x=+m[2],y=+m[3];
     return vertical ? (x>0&&x<b.w*2&&y>=0&&y<b.h*2)
       : (y>0&&y<b.h*2&&x>=0&&x<b.w*2);
   });
   if(!b.halfWalls.length)delete b.halfWalls;
 }
}
function shiftWalls(b,dx,dy){
 if(!Array.isArray(b.walls))return;
 b.walls=b.walls.map(k=>{
   const [x,y]=k.slice(2).split(',').map(Number);
   return wallKey(k[0],x+dx,y+dy);
 });
}
function toggleWall(b,k){
 if(!Array.isArray(b.walls))b.walls=[];
 const i=b.walls.indexOf(k);
 if(i>=0)b.walls.splice(i,1);else b.walls.push(k);
 if(!b.walls.length)delete b.walls;
}

/*
 * A divider may not stop in mid-air: each end has to run into the outer wall
 * or into another divider. An end that stops in the open leaves a fin
 * standing in the box - printable, but never what anyone meant to draw.
 */
/*
 * Where a divider is DRAWN - the same rule Geometry::dividedMesh builds it
 * with, so the picture and the model cannot disagree.
 *
 * On a plain rectangle the divider is centred on the line between the two
 * cells. In an L or a T that line usually continues an outer wall, and there
 * the divider takes that wall's exact place instead, so the two read as one
 * straight wall with one straight face.
 */
/**
 * The wall thickness in pixels, floored so it stays visible when the whole
 * drawer is zoomed out. The box's own wall and its dividers both measure
 * themselves with this, so the two are always drawn the same.
 */
function wallPx(L){ return Math.max(2,getMM('wall')*L.scale); }

function wallRectPx(b,k,L){
 const [c0,c1]=wallCells(k);
 const cells=new Set(cellsOf(b).map(c=>c.x+','+c.y));
 const owns=(x,y)=>cells.has(x+','+y);
 const w=wallPx(L);
 const gap=L.gap;
 if(k[0]==='v'){
   const line=L.ox+c1.x*L.stepX-gap/2;
   let leftOnly=false,rightOnly=false;
   for(let y=b.y;y<b.y+b.h;y++){
     if(owns(c0.x,y)&&!owns(c1.x,y))leftOnly=true;
     if(!owns(c0.x,y)&&owns(c1.x,y))rightOnly=true;
   }
   const x=leftOnly&&!rightOnly?line-gap/2-w:(rightOnly&&!leftOnly?line+gap/2:line-w/2);
   // Along the wall: past the pitch line wherever the box carries on on both
   // sides - half a gap to cross the gap, and half a wall so a crossing with
   // another divider is filled to its far face instead of stopping on its
   // centre line. Where the box ends, it stops at the box.
   const up=owns(c0.x,c0.y-1)&&owns(c1.x,c1.y-1);
   const dn=owns(c0.x,c0.y+1)&&owns(c1.x,c1.y+1);
   const y0=L.oy+c0.y*L.stepY-(up?gap/2+w/2:0);
   const y1=L.oy+c0.y*L.stepY+L.ch+(dn?gap/2+w/2:0);
   return {x:x,y:y0,w:w,h:Math.max(0,y1-y0)};
 }
 const line=L.oy+c1.y*L.stepY-gap/2;
 let upOnly=false,downOnly=false;
 for(let x=b.x;x<b.x+b.w;x++){
   if(owns(x,c0.y)&&!owns(x,c1.y))upOnly=true;
   if(!owns(x,c0.y)&&owns(x,c1.y))downOnly=true;
 }
 const y=upOnly&&!downOnly?line-gap/2-w:(downOnly&&!upOnly?line+gap/2:line-w/2);
 const lf=owns(c0.x-1,c0.y)&&owns(c1.x-1,c1.y);
 const rt=owns(c0.x+1,c0.y)&&owns(c1.x+1,c1.y);
 const x0=L.ox+c0.x*L.stepX-(lf?gap/2+w/2:0);
 const x1=L.ox+c0.x*L.stepX+L.cw+(rt?gap/2+w/2:0);
 return {x:x0,y:y,w:Math.max(0,x1-x0),h:w};
}

/*
 * The colour of a box.
 *
 * A hue picked from where the box sits, stepped so that two boxes touching
 * each other never land on the same one - the old top-left-to-bottom-right
 * gradient gave neighbours almost identical colours and the whole drawer ran
 * together into one shape. While a box is selected the others keep their hue
 * but lose most of their colour, so the selected one is the only lively
 * thing on the board; with nothing selected they are all lively again.
 */
/*
 * Ten hues right round the wheel, not a narrow band of it. Teal to violet
 * looked tasteful and was useless: five of those ten read as "purple" at a
 * glance, so half the drawer still came out the same colour. Only the
 * warning red is left out - an oversize box owns that one.
 */
const BOX_HUES=[168,196,222,250,276,302,330,44,80,128];
function boxTint(b,lively){
 // The hue is a property of the box, never of its current grid coordinates.
 // Moving or resizing must not repaint a user's box as a different object.
 if(!Number.isInteger(b.colorSlot))b.colorSlot=((b.id*7+3)%BOX_HUES.length+BOX_HUES.length)%BOX_HUES.length;
 const i=((b.colorSlot%BOX_HUES.length)+BOX_HUES.length)%BOX_HUES.length;
 const h=BOX_HUES[i];
 const s=lively?44:18;
 const l=lively?63:71;
 return {
   fill:'hsl('+h+' '+s+'% '+l+'%)',
   stroke:'hsl('+h+' '+Math.round(s*0.95)+'% '+Math.round(l*0.55)+'%)',
 };
}

function danglingWall(b){
 if(!hasWalls(b))return null;
 const set=new Set(b.walls);
 const cells=new Set(cellsOf(b).map(c=>c.x+','+c.y));
 const has=(x,y)=>cells.has(x+','+y);
 // Junctions: the corner shared by cells (x-1,y-1),(x,y-1),(x-1,y),(x,y).
 for(let y=b.y+1;y<b.y+b.h;y++){
   for(let x=b.x+1;x<b.x+b.w;x++){
     const arms=[
       ['v',x-1,y-1, has(x-1,y),   has(x,y)  ],   // arm above, and the cells past it
       ['v',x-1,y,   has(x-1,y-1), has(x,y-1)],
       ['h',x-1,y-1, has(x,y-1),   has(x,y)  ],
       ['h',x,y-1,   has(x-1,y-1), has(x-1,y)],
     ].filter(a=>set.has(wallKey(a[0],a[1],a[2])));
     if(arms.length!==1)continue;
     // The lone arm ends here. That is fine only if the box itself ends
     // here too, so the arm butts into the outer wall.
     const [type,ax,ay,c1,c2]=arms[0];
     if(c1&&c2)return wallKey(type,ax,ay);
   }
 }
 return null;
}
function danglingFineWall(b){
 if(!hasHalfWalls(b))return null;
 const set=new Set(b.halfWalls);
 // Existing 1:1 walls are two fine-grid pieces; they are valid joins for a
 // new 2:1 wall placed against them.
 (b.walls||[]).forEach(k=>{
   const [x,y]=k.slice(2).split(',').map(Number);
   if(k[0]==='v'){const xx=(x-b.x+1)*2, yy=(y-b.y)*2;set.add('v:'+xx+','+yy);set.add('v:'+xx+','+(yy+1));}
   else {const xx=(x-b.x)*2, yy=(y-b.y+1)*2;set.add('h:'+xx+','+yy);set.add('h:'+(xx+1)+','+yy);}
 });
 const joins=(x,y,self)=>['v:'+x+','+(y-1),'v:'+x+','+y,'h:'+(x-1)+','+y,'h:'+x+','+y]
   .some(k=>k!==self&&set.has(k));
 for(const key of b.halfWalls){
   const m=/^([vh]):(\d+),(\d+)$/.exec(key);if(!m)continue;
   const v=m[1]==='v',x=+m[2],y=+m[3];
   const a=v?[x,y]:[x,y], z=v?[x,y+1]:[x+1,y];
   if((a[0]>0&&a[0]<b.w*2&&a[1]>0&&a[1]<b.h*2&&!joins(a[0],a[1],key))
      ||(z[0]>0&&z[0]<b.w*2&&z[1]>0&&z[1]<b.h*2&&!joins(z[0],z[1],key)))return key;
 }
 return null;
}
function wallsValid(b){return danglingWall(b)===null&&danglingFineWall(b)===null}
function anyWallsInvalid(){return boxes.some(b=>hasAnyWalls(b)&&!wallsValid(b))}

function occupied(except=null){
 const g=grid(),a=Array.from({length:g.r},()=>Array(g.c).fill(false));
 const mark=c=>{if(c.y>=0&&c.x>=0&&c.y<g.r&&c.x<g.c)a[c.y][c.x]=true};
 boxes.forEach(b=>{
   if(b.id===except)return;
   cellsOf(b).forEach(mark);
 });
 return a;
}
function fits(b,except=null){
 const g=grid(),a=occupied(except);
 for(const c of cellsOf(b)){
   if(c.x<0||c.y<0||c.x>=g.c||c.y>=g.r)return false;
   if(a[c.y][c.x])return false;
 }
 return true;
}
function firstFree(w,h){
 const g=grid(),a=occupied();
 for(let y=0;y<=g.r-h;y++)for(let x=0;x<=g.c-w;x++){let ok=true;for(let yy=y;yy<y+h;yy++)for(let xx=x;xx<x+w;xx++)if(a[yy][xx])ok=false;if(ok)return{x,y}}return null;
}
/**
 * Pointer position in the SVG viewBox.
 *
 * The canvas has a plain 0,0 VIEW_W,VIEW_H viewBox, so its screen rectangle
 * is enough to map a pointer precisely.  Using getScreenCTM().inverse() here
 * made the whole editor fail on hosts that briefly report a zero-size SVG
 * while their ad strip/layout is being inserted: the browser then throws
 * "matrix is not invertible" on the first pointer move.
 */
function pte(e){
 const r=svg.getBoundingClientRect();
 if(r.width<=0||r.height<=0)return {x:0,y:0};
 return {
   x:(e.clientX-r.left)*VIEW_W/r.width,
   y:(e.clientY-r.top)*VIEW_H/r.height,
 };
}

/**
 * Canvas geometry derived from the real drawer.
 *
 * The board used to be stretched to fill a fixed 900x650 area, so a shallow
 * wide drawer was drawn as tall squares and the picture disagreed with the
 * millimetres printed inside it. Everything here is in real proportions:
 * one scale factor, cells sized from the actual cell size, and the gap drawn
 * at the width it will really be.
 */
// The drawing area follows the real size of its container, so a large monitor
// gets a large grid instead of a fixed 900x650 postage stamp in the middle.
let VIEW_W=900,VIEW_H=650;
function syncCanvasSize(){
  if(!svg)return false;
  /*
   * Measured on the <svg> itself, not on its wrapper.
   *
   * The wrapper's clientWidth/Height count its padding. With any padding
   * there the viewBox came out bigger than the element drawing it, the
   * browser scaled the picture down to fit and centred it - which is where
   * the grey strips with no background grid came from: along the bottom on
   * a phone, and left and right on a desktop.
   */
  const r=svg.getBoundingClientRect();
  const w=Math.round(r.width),h=Math.round(r.height);
  if(w<200||h<200)return false;
  if(w===VIEW_W&&h===VIEW_H)return false;
  VIEW_W=w;VIEW_H=h;
  svg.setAttribute('viewBox','0 0 '+w+' '+h);
  return true;
}
syncCanvasSize();
if(window.ResizeObserver&&svg){
  new ResizeObserver(()=>{ if(syncCanvasSize()&&typeof draw==='function') draw(); }).observe(svg);
}else{
  window.addEventListener('resize',()=>{ if(syncCanvasSize()&&typeof draw==='function') draw(); });
}

// Free view: zoom factor plus a pan offset, baked straight into the layout
// numbers below. Everything - boxes, arrows, and the background grid that
// spans the whole canvas - follows automatically at every zoom level.
const VIEWSTATE={z:1,tx:0,ty:0};

/**
 * The outer inset in millimetres, clamped so it can never swallow the
 * drawer it is measured from.
 */
function outerMM(dw,dd){
  const raw=Math.max(0,getMM('outer')||0);
  const room=Math.max(0,Math.min(dw,dd)/2-1);
  return Math.min(raw,room);
}

function layout(){
  const g=grid();
  // Extra room at the bottom keeps the grid (and its ghost + squares) clear of
  // the floating tool strip, which would otherwise cover the bottom row.
  // Room reserved around the board inside the drawing itself. The bottom
  // keeps the grid clear of the floating tool strip; the top and the sides
  // leave the + / - handles some air under the panel above and beside the
  // rails, instead of having them touch the edge of the canvas.
  const W=VIEW_W,H=VIEW_H,PX=64,PT=68,PB=92;

  const drawerW=Math.max(1,getMM('dw'));
  const drawerD=Math.max(1,getMM('dd'));
  const gapMM=Math.max(0,getMM('gap'));
  // The margin left free all the way round the layout. A 250 mm drawer with
  // 1 mm here is filled as if it were 248 mm, so the finished set drops in
  // with room to spare instead of having to be pressed against the walls.
  const outMM=outerMM(drawerW,drawerD);

  // Fit the drawer into the canvas without distorting it.
  const base=Math.min((W-PX*2)/drawerW,(H-PT-PB)/drawerD);
  const z=VIEWSTATE.z;
  const scale=base*z;

  const boardW=drawerW*scale, boardH=drawerD*scale;
  // Zoom happens around the canvas centre; the pan offset then shifts the
  // whole picture freely.
  const bx=(W-boardW)/2+VIEWSTATE.tx;
  const by=PT+((H-PT-PB)-drawerD*base)/2+(drawerD*base-boardH)/2+VIEWSTATE.ty;

  // bx/by outline the drawer; ox/oy start the grid, one inset in from it.
  const ox=bx+outMM*scale;
  const oy=by+outMM*scale;

  const cellW=(drawerW-2*outMM-(g.c-1)*gapMM)/g.c;
  const cellD=(drawerD-2*outMM-(g.r-1)*gapMM)/g.r;

  return {
    g, ox, oy, bx, by, outer:outMM, scale, boardW, boardH,
    gap:gapMM*scale,
    cw:cellW*scale, ch:cellD*scale,
    stepX:(cellW+gapMM)*scale,
    stepY:(cellD+gapMM)*scale,
    // In-canvas UI scale: labels, +/- squares and buttons sized for a 1080p
    // canvas shrink to unreadable on a 1440p/4K monitor, so everything drawn
    // on top of the board follows the canvas size (capped at 1.8x).
    ui:Math.max(1,Math.min(1.8,Math.min(VIEW_W,VIEW_H)/680)),
  };
}

/** Pixel rectangle of a box, gaps included. */
function boxRect(b,L){
  return {
    x:L.ox+b.x*L.stepX,
    y:L.oy+b.y*L.stepY,
    w:b.w*L.cw+(b.w-1)*L.gap,
    h:b.h*L.ch+(b.h-1)*L.gap,
  };
}

/**
 * Park the selection bubble against the box it belongs to.
 *
 * It lives in a clear lane OUTSIDE the grid rather than in a neighbouring
 * cell: all four directions around a selected box can then keep their own
 * +/- actions available.  When no outer lane is visible at the current zoom,
 * it uses the canvas header and deliberately drops its misleading tail.
 */
function placeSelBubble(b){
  const el=$('selTools');
  if(!el||el.hidden||!b)return;
  const host=svg.closest?svg.closest('.center'):null;
  const m=svg.getScreenCTM();
  if(!host||!m)return;

  const L=layout();
  const r=boxRect(b,L);
  const at=(x,y)=>{const p=svg.createSVGPoint();p.x=x;p.y=y;return p.matrixTransform(m)};
  const tl=at(r.x,r.y), br=at(r.x+r.w,r.y+r.h);
  const boardTL=at(L.bx,L.by),boardBR=at(L.bx+L.boardW,L.by+L.boardH);
  const hb=host.getBoundingClientRect();
  const bw=el.offsetWidth, bh=el.offsetHeight;
  // The actual tool groups, rather than their full-width overlay parent.
  // Park the selected-box card next to them in the canvas' reserved bottom
  // lane so it never competes with cells, resize zones or wall controls.
  const toolStrip=$('canvasTools');
  const toolGroups=toolStrip?[toolStrip.querySelector('.ct-left'),toolStrip.querySelector('.ct-right')].filter(Boolean):[];
  if(toolGroups.length){
    const rects=toolGroups.map(group=>group.getBoundingClientRect());
    const left=Math.min(...rects.map(rect=>rect.left))-hb.left;
    const right=Math.max(...rects.map(rect=>rect.right))-hb.left;
    const top=Math.min(...rects.map(rect=>rect.top))-hb.top;
    const bottom=Math.max(...rects.map(rect=>rect.bottom))-hb.top;
    const leftSpot=left-bw-10,rightSpot=right+10;
    const x=leftSpot>=8?leftSpot:(rightSpot+bw<=host.clientWidth-8?rightSpot:null);
    if(x!==null){
      const y=Math.max(6,Math.min(top+(bottom-top-bh)/2,host.clientHeight-bh-6));
      el.style.left=Math.round(x)+'px';
      el.style.top=Math.round(y)+'px';
      el.classList.remove('below');el.classList.add('detached');
      el.style.setProperty('--tail','50%');
      el.classList.toggle('is-dragging',!!drag);
      return;
    }
  }
  const midX=(tl.x+br.x)/2-hb.left;
  const boardTop=boardTL.y-hb.top,boardBottom=boardBR.y-hb.top;
  const topLane=boardTop-bh-10;
  const bottomLane=boardBottom+10;
  // Bottom contains the floating canvas toolbar, hence the larger reserve.
  const topOK=topLane>=6;
  const bottomOK=bottomLane<=host.clientHeight-bh-58;
  let y,detached=true;
  if(topOK){
    y=topLane;
  }else if(bottomOK){
    y=bottomLane;
  }else{
    // A zoomed board can fill the whole canvas. The slim header is still
    // outside its cells, unlike any position alongside the selected box.
    y=6;
  }
  const x=Math.max(8,Math.min(midX-bw/2,host.clientWidth-bw-8));

  el.style.left=Math.round(x)+'px';
  el.style.top=Math.round(y)+'px';
  el.classList.toggle('below',false);
  el.classList.toggle('detached',detached);
  el.style.setProperty('--tail',Math.round(Math.max(14,Math.min(midX-x,bw-14)))+'px');
  // Mid-drag it follows the box, but faintly and click-through, so it never
  // lands under the pointer that is doing the dragging.
  el.classList.toggle('is-dragging',!!drag);
}

/**
 * SVG path of a free-shape box, drawn EXACTLY like the exported solid: the
 * envelope of the shape's grid pitches pulled in by half a gap, so an L
 * meets itself cleanly in the corner, and convex corners rounded with the
 * configured radius - what you see is what gets printed.
 */
function polyPath(b,L){
 const set=new Set(cellsOf(b).map(c=>c.x+','+c.y));
 const has=(x,y)=>set.has(x+','+y);
 // Pitch-lattice boundary loop, interior kept on the left of travel.
 const edges=new Map();
 const addE=(x1,y1,x2,y2)=>{
   const k=x1+','+y1;
   if(!edges.has(k))edges.set(k,[]);
   edges.get(k).push([x2,y2]);
 };
 for(const key of set){
   const [x,y]=key.split(',').map(Number);
   if(!has(x,y-1))addE(x,y,x+1,y);
   if(!has(x+1,y))addE(x+1,y,x+1,y+1);
   if(!has(x,y+1))addE(x+1,y+1,x,y+1);
   if(!has(x-1,y))addE(x,y+1,x,y);
 }
 if(!edges.size)return '';
 // Every boundary loop: the outside, plus one per enclosed hole in a ring.
 const lattices=[];
 let outerGuard=64;
 while(edges.size&&outerGuard--){
   const startKey=edges.keys().next().value;
   let [cx,cy]=startKey.split(',').map(Number);
   const lat=[];
   let guard=4096,prev=null;
   while(guard--){
     const k=cx+','+cy,list=edges.get(k);
     if(!list||!list.length)break;

     /*
      * A shape can touch itself corner to corner, and then this vertex has
      * two ways out. Taking whichever was recorded first drew the two parts
      * as one outline, so on screen they hung together by the corner while
      * the exported solid had them apart - the reported "joined corners".
      *
      * Same rule as Geometry::traceLoops on the server: sharpest right
      * turn. That walks the parts as separate outlines, each then pulled in
      * by half a gap, leaving the gap the printed model really has.
      */
     let pick=0;
     if(list.length>1&&prev){
       const inAng=Math.atan2(cy-prev[1],cx-prev[0]);
       let best=Infinity;
       list.forEach(([tx,ty],idx)=>{
         let turn=inAng-Math.atan2(ty-cy,tx-cx);
         while(turn<=-Math.PI)turn+=2*Math.PI;
         while(turn>Math.PI)turn-=2*Math.PI;
         if(turn<best){best=turn;pick=idx;}
       });
     }

     const [nx,ny]=list[pick];
     list.splice(pick,1);
     if(!list.length)edges.delete(k);
     lat.push([cx,cy]);
     prev=[cx,cy];
     cx=nx;cy=ny;
     if(cx+','+cy===startKey)break;
   }
   if(lat.length>=4)lattices.push(lat);
 }
 let out='';
 lattices.forEach(lat=>{out+=polySubPath(lat,L);});
 return out;
}

/** One boundary loop of the lattice, mapped to the drawn outline. */
function polySubPath(lat,L){
 // Collinear lattice points merged, then mapped to pixels: pitch line i is
 // the middle of the gap, and every vertex slides half a gap toward the
 // interior along both of its edges' inward normals.
 const merged=[];
 const n=lat.length;
 for(let i=0;i<n;i++){
   const p=lat[(i+n-1)%n],q=lat[i],r=lat[(i+1)%n];
   if(Math.abs((q[0]-p[0])*(r[1]-q[1])-(q[1]-p[1])*(r[0]-q[0]))>1e-9)merged.push(q);
 }
 const px=i=>L.ox+i*L.stepX-L.gap/2;
 const py=j=>L.oy+j*L.stepY-L.gap/2;
 const m=merged.length;
 const pts=[];
 for(let i=0;i<m;i++){
   const p=merged[(i+m-1)%m],q=merged[i],r=merged[(i+1)%m];
   const v1=[q[0]-p[0],q[1]-p[1]],v2=[r[0]-q[0],r[1]-q[1]];
   const l1=Math.hypot(v1[0],v1[1]),l2=Math.hypot(v2[0],v2[1]);
   // Inward normal of a boundary edge (interior on the left of travel, in
   // screen coordinates with y pointing down) is (-y, x).
   const n1=[-v1[1]/l1,v1[0]/l1],n2=[-v2[1]/l2,v2[0]/l2];
   // The mitre needs the (1 + n1.n2) divisor, or a point lying along a
   // straight edge is pushed twice as far as the offset asks - the same
   // fault the exporter had, where it showed up as a wedge on a wall.
   const mitre=1+n1[0]*n2[0]+n1[1]*n2[1];
   const k=mitre>1e-9?L.gap/2/mitre:L.gap/2;
   pts.push({
     x:px(q[0])+(n1[0]+n2[0])*k,
     y:py(q[1])+(n1[1]+n2[1])*k,
     convex:(v1[0]*v2[1]-v1[1]*v2[0])>0,
   });
 }
 // Match the exported contour: both kinds of corner are rounded, so a box
 // standing in the notch of a free shape meets a rounded edge, not a spike.
 const radiusMM=Number(getMM('radius'));
 // An empty / zero radius is deliberately a sharp free-shape outline.  It
 // must never leave a residual SVG arc behind from a previous rounded draw.
 const R=Number.isFinite(radiusMM)&&radiusMM>0?radiusMM*L.scale:0;
 const seg=[];
 for(let i=0;i<m;i++){
   const q=pts[i];
   const prev=pts[(i+m-1)%m],next=pts[(i+1)%m];
   const l1=Math.hypot(q.x-prev.x,q.y-prev.y);
   const l2=Math.hypot(next.x-q.x,next.y-q.y);
   const rr=Math.min(R,l1/2,l2/2);
   if(rr<0.4||l1<1e-6||l2<1e-6){
     seg.push(q.x.toFixed(2)+' '+q.y.toFixed(2));
     continue;
   }
   const u1=[(q.x-prev.x)/l1,(q.y-prev.y)/l1];
   const u2=[(next.x-q.x)/l2,(next.y-q.y)/l2];
   seg.push((q.x-u1[0]*rr).toFixed(2)+' '+(q.y-u1[1]*rr).toFixed(2)
     +'A'+rr.toFixed(2)+' '+rr.toFixed(2)+' 0 0 '+(q.convex?'1':'0')+' '
     +(q.x+u2[0]*rr).toFixed(2)+' '+(q.y+u2[1]*rr).toFixed(2));
 }
 return seg.length?'M'+seg.join('L')+'Z':'';
}

/** Grid cell under a point, or null. */
function cellAt(p,L){
  const x=Math.floor((p.x-L.ox)/L.stepX);
  const y=Math.floor((p.y-L.oy)/L.stepY);
  return (x>=0&&y>=0&&x<L.g.c&&y<L.g.r)?{x,y}:null;
}

function draw(){
 const L=layout();
 const g=L.g;
 const cw=L.cw, ch=L.ch;
 svg.innerHTML='';
 hasOversize=false;

 // Faint grid across the whole canvas, spaced and aligned exactly like the
 // drawer's cells, so the board reads as part of one continuous sheet.
 {
   const NS='http://www.w3.org/2000/svg';
   const bg=document.createElementNS(NS,'g');
   bg.setAttribute('class','bg-grid');
   const sx=L.stepX>4?L.stepX:0, sy=L.stepY>4?L.stepY:0;
   if(sx){
     for(let x=L.ox%sx;x<=VIEW_W;x+=sx){
       const ln=document.createElementNS(NS,'line');
       ln.setAttribute('x1',x);ln.setAttribute('y1',0);
       ln.setAttribute('x2',x);ln.setAttribute('y2',VIEW_H);
       bg.appendChild(ln);
     }
   }
   if(sy){
     for(let y=L.oy%sy;y<=VIEW_H;y+=sy){
       const ln=document.createElementNS(NS,'line');
       ln.setAttribute('x1',0);ln.setAttribute('y1',y);
       ln.setAttribute('x2',VIEW_W);ln.setAttribute('y2',y);
       bg.appendChild(ln);
     }
   }
   svg.appendChild(bg);
 }

 // The drawer itself, so the proportions are visible even when empty. It is
 // drawn from bx/by: with an outer inset the grid starts further in, and the
 // strip between the two is exactly the margin that will be left free.
 let board=document.createElementNS('http://www.w3.org/2000/svg','rect');
 board.setAttribute('x',L.bx);board.setAttribute('y',L.by);
 board.setAttribute('width',L.boardW);board.setAttribute('height',L.boardH);
 board.setAttribute('class','drawer-outline');
 svg.appendChild(board);

 // The inset made visible: a dashed line where the boxes actually stop.
 if(L.outer>0){
   const inset=document.createElementNS('http://www.w3.org/2000/svg','rect');
   inset.setAttribute('x',L.ox);
   inset.setAttribute('y',L.oy);
   inset.setAttribute('width',Math.max(0,L.boardW-2*L.outer*L.scale));
   inset.setAttribute('height',Math.max(0,L.boardH-2*L.outer*L.scale));
   inset.setAttribute('class','inset-outline');
   svg.appendChild(inset);
 }

 const occ=occupied();
 // Resize handles are drawn directly on the selected box wall, so empty
 // neighbouring cells keep their normal green add-box action.

 // Grid and permanent add buttons on every empty cell.
 for(let y=0;y<g.r;y++)for(let x=0;x<g.c;x++){
   let r=document.createElementNS('http://www.w3.org/2000/svg','rect');
   r.setAttribute('x',L.ox+x*L.stepX);r.setAttribute('y',L.oy+y*L.stepY);
   r.setAttribute('width',Math.max(0,cw));r.setAttribute('height',Math.max(0,ch));
   r.setAttribute('class','cell');svg.appendChild(r);

   if(!occ[y][x]){
     let plus=document.createElementNS('http://www.w3.org/2000/svg','text');
     plus.setAttribute('x',L.ox+x*L.stepX+cw/2);
     plus.setAttribute('y',L.oy+y*L.stepY+ch/2);
     plus.setAttribute('class','cell-action cell-add'+
       (hoverCell&&hoverCell.x===x&&hoverCell.y===y?' cell-add-hover':''));
     plus.style.fontSize=(30*L.ui)+'px';
     plus.dataset.add='1';
     plus.dataset.x=x;
     plus.dataset.y=y;
     plus.textContent='+';
     svg.appendChild(plus);
   }
 }

 // One ghost "+" per side, centred on the grid edge: a click grows the grid on
 // that side. Signed-in only. A side disappears once it hits the 15 limit.
 if(window.H3D_QUOTA && window.H3D_QUOTA.signedIn){
   const NS='http://www.w3.org/2000/svg', gap=8*L.ui, gs=24*L.ui, o=gs/2+3;
   // Anchored to the drawer outline, not the grid: they add a column to the
   // drawer, and hanging them off the inset would make them drift whenever
   // the margin changed.
   const cx=L.bx+L.boardW/2, cy=L.by+L.boardH/2;
   const topRoom=L.by>=gs+gap, sideRoom=L.bx>=gs+gap;
   const mk=(px,py,side,minus)=>{
     const rr=document.createElementNS(NS,'rect');
     rr.setAttribute('x',px-gs/2);rr.setAttribute('y',py-gs/2);
     rr.setAttribute('width',gs);rr.setAttribute('height',gs);rr.setAttribute('rx',6);
     rr.setAttribute('class','ghost-add'+(minus?' ghost-rm':''));rr.dataset.ghost=side+(minus?':rm':'');
     svg.appendChild(rr);
     // Drawn as strokes rather than a text glyph: font baselines put "+" and
     // "-" visibly off-centre, a pair of lines is dead centre by construction.
     const arm=4.5*L.ui;
     const gl=document.createElementNS(NS,'path');
     gl.setAttribute('d','M '+(px-arm)+' '+py+' H '+(px+arm)
                    +(minus?'':' M '+px+' '+(py-arm)+' V '+(py+arm)));
     gl.setAttribute('class','ghost-plus');
     svg.appendChild(gl);
   };
   const rightX=L.bx+L.boardW+gap+gs/2, leftX=L.bx-gap-gs/2;
   const botY=L.by+L.boardH+gap+gs/2, topY=L.by-gap-gs/2;
   if(sideRoom){
     if(g.c<MAXCELLS){ mk(rightX,cy-o,'col-right',false); mk(leftX,cy-o,'col-left',false); }
     if(g.c>1){  mk(rightX,cy+o,'col-right',true);  mk(leftX,cy+o,'col-left',true);  }
   }
   if(g.r<MAXCELLS){ mk(cx-o,botY,'row-bottom',false); }
   if(g.r>1){  mk(cx+o,botY,'row-bottom',true);  }
   if(topRoom){
     if(g.r<MAXCELLS){ mk(cx-o,topY,'row-top',false); }
     if(g.r>1){  mk(cx+o,topY,'row-top',true);  }
   }
 }

 // Controls of the selected box (outline, handles, grow/shrink pills) are
 // collected here and stacked on top AFTER every box is drawn - SVG paints
 // in document order, so a neighbour drawn later used to cover the pills.
 const selUI=document.createElementNS('http://www.w3.org/2000/svg','g');

 boxes.forEach(b=>{
   const R=boxRect(b,L);
   const poly=isPoly(b);

   // One scale for everything, so the corner radius is drawn at the size it
   // will actually be printed rather than an approximation of it.
   const configuredRadius=Number(getMM('radius'));
   const radiusPx=Math.min(
     (Number.isFinite(configuredRadius)&&configuredRadius>0?configuredRadius:0)*L.scale,
     Math.max(0,R.w/2),
     Math.max(0,R.h/2)
   );

   let q;
   if(poly){
     // A free shape is one path around the whole blob of cells.
     q=document.createElementNS('http://www.w3.org/2000/svg','path');
     q.setAttribute('d',polyPath(b,L));
     q.setAttribute('stroke-linejoin','round');
   }else{
     q=document.createElementNS('http://www.w3.org/2000/svg','rect');
     q.setAttribute('x',R.x);q.setAttribute('y',R.y);
     q.setAttribute('width',Math.max(1,R.w));q.setAttribute('height',Math.max(1,R.h));
     q.setAttribute('rx',radiusPx);
     q.setAttribute('ry',radiusPx);
   }
   // With something selected, everything else steps back a little. Dimming
   // the rest is what makes the selected one obvious at a glance, more than
   // any outline on the box itself can.
   q.setAttribute('class','box'
     + (b.id===selected ? ' sel' : (selected!==null ? ' unsel' : '')));
   q.dataset.id=b.id;
   svg.appendChild(q);


   // Labels and buttons of a free shape anchor to the cell nearest its
   // centre - the geometric centre of an L can fall on empty air.
   let LRc=R;
   if(poly){
     const bcx=b.x+b.w/2-0.5,bcy=b.y+b.h/2-0.5;
     let best=null,bd=Infinity;
     cellsOf(b).forEach(c=>{
       const dd=(c.x-bcx)*(c.x-bcx)+(c.y-bcy)*(c.y-bcy);
       if(dd<bd){bd=dd;best=c;}
     });
     LRc={x:L.ox+best.x*L.stepX,y:L.oy+best.y*L.stepY,w:L.cw,h:L.ch};
   }

   const cx=LRc.x+LRc.w/2, cy=LRc.y+LRc.h/2;

   // Inner bounds of the box - used to centre the whole label stack.
   const boxLeft=LRc.x+3;
   const boxTop=LRc.y+3;
   const boxRight=LRc.x+LRc.w-3;
   const boxBottom=LRc.y+LRc.h-3;
   const boxW=boxRight-boxLeft;
   const boxH=boxBottom-boxTop;

   // One source for the real millimetres of a box: it already subtracts the
   // outer inset, which this used to work out for itself and get wrong.
   const ps=physSize(b.w,b.h);
   const physicalW=ps.pW,physicalD=ps.pD;

   // Flag a box that will not fit the printer bed, and remember it so the
   // status line and the export both know something is wrong.
   const over=overMax(physicalW,physicalD);
   if(over){
     hasOversize=true;
     q.setAttribute('class',q.getAttribute('class')+' oversize');
   }

   const isSel=(b.id===selected);

   // Positional colour: teal in the top-left flowing to violet in the
   // bottom-right, matching the studio mockup. Oversize boxes keep their red
   // warning fill; a selected box keeps the white .sel outline, so only the
   // fill is themed for it.
   // Selected box in colour, the rest stepped back into grey; nothing
   // selected and they are all in colour. Inline, because the stylesheet
   // could not override a fill that is set inline anyway.
   const tint=boxTint(b,isSel||selected===null);
   if(!over){
     q.style.fill=tint.fill;
     // A selected box is the active editing surface. Let the other boxes
     // step back so the Free-mode cell actions never look like they belong
     // to a neighbour.
     q.style.fillOpacity=(selected!==null&&!isSel)?'.40':'1';
     q.style.stroke=tint.stroke;
   }

   /*
    * The box's own wall, at the thickness that will be printed.
    *
    * Without it the drawing was a flat coloured tile with a hairline round
    * it, and a divider drawn at its real thickness stood in the middle of
    * that making no sense - the box it divides had no walls to join. Now the
    * outline IS the wall, the divider is the same band, and what is on
    * screen is what comes out of the printer.
    *
    * Drawn as a stroke of twice the thickness clipped to the box, so the
    * inner half survives and lands exactly on the footprint edge. That works
    * on an L or a T with no path arithmetic - which offsetting the outline
    * by hand would need, corner by corner.
    */
   if(!over){
     const NSW='http://www.w3.org/2000/svg';
     const cid='boxwall'+b.id;
     const clip=document.createElementNS(NSW,'clipPath');
     clip.setAttribute('id',cid);
     clip.appendChild(q.cloneNode(false));
     svg.appendChild(clip);

     const band=q.cloneNode(false);
     band.removeAttribute('data-id');
     band.setAttribute('class','box-wall');
     band.setAttribute('clip-path','url(#'+cid+')');
     band.style.fill='none';
     band.style.stroke=tint.stroke;
     band.style.strokeWidth=2*wallPx(L);
     band.style.fillOpacity='';
     svg.appendChild(band);

     // The box needs no outline of its own on top of that. Only the selected
     // one keeps its ring, which is the selection, not a wall.
     if(!isSel) q.style.strokeWidth='0';
   }

   /*
    * Dividers, drawn exactly where they get built (wallRectPx follows
    * Geometry::dividedMesh): one wall thick, on the line between two cells,
    * and taking the outer wall's place where it continues one.
    */
   if(wallModeOn()&&hasAnyWalls(b)){
     const NSD='http://www.w3.org/2000/svg';
     const gDiv=document.createElementNS(NSD,'g');
     gDiv.setAttribute('class','box-dividers');
     (b.walls||[]).forEach(k=>{
       const rc=wallRectPx(b,k,L);
       const r=document.createElementNS(NSD,'rect');
       r.setAttribute('x',rc.x);r.setAttribute('y',rc.y);
       r.setAttribute('width',rc.w);r.setAttribute('height',rc.h);
       r.setAttribute('class','box-divider');
       // The box's own outline colour: a divider IS a wall of this box, and
       // in white it shouted louder than the box it stands in.
       r.style.fill=over?'':tint.stroke;
       gDiv.appendChild(r);
     });
     // Same rule as the merge: these two kinds are rectangle-only. A box
     // loaded from a saved project, or restored by Undo, must not be able
     // to paint a wall across cells it does not own.
     (isPoly(b)?[]:(b.midWalls||[])).forEach(k=>{
       const vertical=k==='m:v',w=wallPx(L);
       const r=document.createElementNS(NSD,'rect');
       r.setAttribute('x',vertical?R.x+R.w/2-w/2:R.x);
       r.setAttribute('y',vertical?R.y:R.y+R.h/2-w/2);
       r.setAttribute('width',vertical?w:R.w);
       r.setAttribute('height',vertical?R.h:w);
       r.setAttribute('class','box-divider');
       r.style.fill=over?'':tint.stroke;
       gDiv.appendChild(r);
     });
     // Fine (2:1) dividers are stored as individual half-cell segments for
     // editing. Drawing those individual rectangles left anti-aliased seams
     // and a visible little "tooth" where two halves met. Merge every
     // collinear run for display only; the stored/editable segments stay
     // exactly as they are and the preview becomes one clean wall.
     const fineRuns={v:new Map(),h:new Map()};
     (isPoly(b)?[]:(b.halfWalls||[])).forEach(k=>{
       const m=/^([vh]):(\d+),(\d+)$/.exec(k);if(!m)return;
       const axis=m[1],line=+m[2],part=+m[3];
       if(!fineRuns[axis].has(line))fineRuns[axis].set(line,new Set());
       fineRuns[axis].get(line).add(part);
     });
     const drawFineRun=(vertical,line,start,length)=>{
       const w=wallPx(L),r=document.createElementNS(NSD,'rect');
       const x=vertical?R.x+R.w*line/(b.w*2)-w/2:R.x+R.w*start/(b.w*2);
       const y=vertical?R.y+R.h*start/(b.h*2):R.y+R.h*line/(b.h*2)-w/2;
       r.setAttribute('x',x);r.setAttribute('y',y);
       r.setAttribute('width',vertical?w:R.w*length/(b.w*2));
       r.setAttribute('height',vertical?R.h*length/(b.h*2):w);
       r.setAttribute('class','box-divider');r.style.fill=over?'':tint.stroke;
       gDiv.appendChild(r);
     };
     [['v',true],['h',false]].forEach(([axis,vertical])=>{
       fineRuns[axis].forEach((parts,line)=>{
         const ordered=[...parts].sort((a,c)=>a-c);
         let start=null,previous=null;
         ordered.forEach(part=>{
           if(start===null){start=previous=part;return;}
           if(part===previous+1){previous=part;return;}
           drawFineRun(vertical,line,start,previous-start+1);
           start=previous=part;
         });
         if(start!==null)drawFineRun(vertical,line,start,previous-start+1);
       });
     });
     svg.appendChild(gDiv);
   }

   /* ---- Vertically centred content stack: "1 × 2" / dimensions / REMOVE ---- */
   const titleFS = ((b.w===1&&b.h===1)?15:17)*L.ui;
   const sizeFS  = (boxW<64?8:(boxW<110?9:10))*L.ui;

   /*
    * A selected box carries no label of its own.
    *
    * Selecting it covers it in handles - a minus on every edge segment, an
    * arrow on every side - and the label sat underneath them in the middle,
    * with the delete button on top of that. Three things fighting for the
    * same few square centimetres.
    *
    * Nothing is lost by dropping them: the panel above the drawing says
    * what is selected and how big it is, and carries the duplicate and
    * delete buttons. So while a box is selected the drawing shows only its
    * handles, and the moment it is deselected the label comes back.
    */
   const GAP_TITLE_SIZE = 3;

   const rows=[];
   let budget=boxH-4;

   const showTitle = !isSel && budget>=titleFS+GAP_TITLE_SIZE && boxW>=42;
   if(showTitle){
     rows.unshift({k:'title', h:titleFS, gap:0});
     budget-=titleFS+GAP_TITLE_SIZE;
   }

   const showSize = showTitle && boxW>=30 && budget>=sizeFS+GAP_TITLE_SIZE;
   if(showSize){
     rows.splice(1,0,{k:'size', h:sizeFS, gap:GAP_TITLE_SIZE});
   }

   const stackH=rows.reduce((s,r)=>s+r.h+r.gap,0);
   let cursorY=cy-stackH/2;
   const rowY={};
   rows.forEach(r=>{cursorY+=r.gap;rowY[r.k]=cursorY+r.h/2;cursorY+=r.h;});

   // Title: 1 × 2
   if(showTitle){
   let titleEl=document.createElementNS('http://www.w3.org/2000/svg','text');
   titleEl.setAttribute('x',cx);titleEl.setAttribute('y',rowY.title);titleEl.setAttribute('fill','#fff');
   titleEl.setAttribute('font-weight','700');
   titleEl.style.fontSize=titleFS+'px';titleEl.style.textAnchor='middle';titleEl.style.dominantBaseline='central';
   // A free shape is not W x H anything - what matters is how many cells it
   // spans, so that is the whole label.
   titleEl.textContent=poly?String(cellsOf(b).length):b.w+' × '+b.h;
   titleEl.style.pointerEvents='none';svg.appendChild(titleEl);
   }

   // Real dimensions
   if(showSize){
     let st=document.createElementNS('http://www.w3.org/2000/svg','text');
     st.setAttribute('x',cx);st.setAttribute('y',rowY.size);
     st.setAttribute('class','box-size-label');
     st.style.fontSize=sizeFS+'px';
     st.style.dominantBaseline='central';
     st.style.textAnchor='middle';
     // Narrow boxes get a tight, unit-less, whole-number size so it still fits
     // (e.g. "55×224"); roomier ones keep the full precise label.
     const compact=boxW<74;
     const num=mm=>compact?String(Math.round(fromMM(mm))):formatUnit(mm);
     // A free shape is not a rectangle, so its size is the space it needs -
     // marked with a tilde to read as the rough figure it is.
     st.textContent=(poly?'~ ':'')
       +num(physicalW)+(compact?'×':' × ')+num(physicalD)+(compact?'':' '+currentUnit());
     svg.appendChild(st);
   }

   if(b.id===selected){

     // A subtle selected outline instead of bulky controls. A free shape
     // gets its whole blob outlined, not just the label cell.
     let selLine;
     if(poly){
       selLine=document.createElementNS('http://www.w3.org/2000/svg','path');
       selLine.setAttribute('d',polyPath(b,L));
     }else{
       selLine=document.createElementNS('http://www.w3.org/2000/svg','rect');
       selLine.setAttribute('x',R.x+3);
       selLine.setAttribute('y',R.y+3);
       selLine.setAttribute('width',R.w-6);
       selLine.setAttribute('height',R.h-6);
       // Match the selected outline to the real box radius.  The former
       // 12px cap made a large rounded box look stepped/square as soon as
       // divider editing selected it.
       selLine.setAttribute('rx',Math.min(radiusPx,Math.max(0,Math.min(R.w-6,R.h-6)/2)));
       selLine.setAttribute('ry',Math.min(radiusPx,Math.max(0,Math.min(R.w-6,R.h-6)/2)));
     }
     selLine.setAttribute('class','selected-inner-outline');
     selLine.setAttribute('pointer-events','none');
     selUI.appendChild(selLine);

     // Free edit works cell by cell. A fine inner grid makes the active box
     // legible as cells while the rest of the layout remains deliberately
     // subdued.
     if(shapeEditOn()){
       const NSO='http://www.w3.org/2000/svg';
       cellsOf(b).forEach(c=>{
         const cellLine=document.createElementNS(NSO,'rect');
         cellLine.setAttribute('x',L.ox+c.x*L.stepX+1);
         cellLine.setAttribute('y',L.oy+c.y*L.stepY+1);
         cellLine.setAttribute('width',Math.max(0,L.cw-2));
         cellLine.setAttribute('height',Math.max(0,L.ch-2));
         cellLine.setAttribute('class','selected-cell-outline');
         selUI.appendChild(cellLine);
       });
     }

    // ---- Resize handles ----
     // Each edge is grabbable along its whole length, not just at the
     // corners. Corners are drawn last so they win the hit test where the
     // two zones overlap, which keeps diagonal resizing reachable.
     const NS='http://www.w3.org/2000/svg';

     const hL=R.x;
     const hT=R.y;
     const hR=R.x+Math.max(1,R.w);
     const hB=R.y+Math.max(1,R.h);
     const sideW=hR-hL;
     const sideH=hB-hT;

     // Touch needs a fatter target than a mouse.
     const coarse=window.matchMedia&&window.matchMedia('(pointer: coarse)').matches;
     const EDGE=coarse?20:12;              // edge strip thickness
     const CORNER=Math.min(coarse?28:19,sideW/2,sideH/2);
     const GRIP=3;                         // visible bar thickness

     const addHandle=(dir,hit,grip,dot)=>{
       const grp=document.createElementNS(NS,'g');
       grp.setAttribute('class','handle-group resize-'+dir);

       if(grip&&Math.max(grip.w,grip.h)>6&&Math.min(grip.w,grip.h)>0){
         const bar=document.createElementNS(NS,'rect');
         bar.setAttribute('x',grip.x);bar.setAttribute('y',grip.y);
         bar.setAttribute('width',grip.w);bar.setAttribute('height',grip.h);
         bar.setAttribute('rx',Math.min(grip.w,grip.h)/2);
         bar.setAttribute('class','edge-grip');
         grp.appendChild(bar);
       }

       const r=document.createElementNS(NS,'rect');
       r.setAttribute('x',hit.x);r.setAttribute('y',hit.y);
       r.setAttribute('width',hit.w);r.setAttribute('height',hit.h);
       r.setAttribute('class','resize-handle');
       r.dataset.id=b.id;r.dataset.resize='1';r.dataset.resizeDir=dir;
       grp.appendChild(r);

       // The corner dot lives inside the group so it can react to hover.
       if(dot){
         const c=document.createElementNS(NS,'circle');
         c.setAttribute('cx',dot.x);
         c.setAttribute('cy',dot.y);
         c.setAttribute('r',4);
         c.setAttribute('class','resize-corner');
         grp.appendChild(c);
       }

       selUI.appendChild(grp);
     };

     // A 1×1 box has no cell left to shrink. Keep the direct remove-minus
     // available to both the rectangular and Free-mode render paths. It is
     // deliberately defined outside the !poly block because Free Mode also
     // uses it for a one-cell shape.
     const mkBoxRemoveIcon=()=>{
       const gx=cx,gy=cy;
       const grp=document.createElementNS(NS,'g');
       grp.setAttribute('class','grow-btn edge-shrink-icon box-remove-icon');
       grp.dataset.id=b.id;grp.dataset.removeBox='1';grp.dataset.dir='center';
       const hit=document.createElementNS(NS,'circle');
       hit.setAttribute('cx',gx);hit.setAttribute('cy',gy);hit.setAttribute('r',15*L.ui);
       hit.setAttribute('class','grow-hit');grp.appendChild(hit);
       const dot=document.createElementNS(NS,'circle');
       dot.setAttribute('cx',gx);dot.setAttribute('cy',gy);dot.setAttribute('r',9*L.ui);
       dot.setAttribute('class','edge-shrink-dot');grp.appendChild(dot);
       const arm=4*L.ui;
       const sign=document.createElementNS(NS,'path');
       sign.setAttribute('d','M '+(gx-arm)+' '+gy+' H '+(gx+arm));
       sign.setAttribute('class','edge-shrink-sign');grp.appendChild(sign);
       const tt=document.createElementNS(NS,'title');tt.textContent=t('box.remove');grp.appendChild(tt);
       selUI.appendChild(grp);
     };

     // Edge-drag resizing only makes sense on a rectangle; a free shape is
     // sculpted cell by cell with the +/- pills instead.
     if(!poly){
     // Horizontal edges. Skipped when the corners already cover the side.
     const spanX=sideW-2*CORNER;
     if(spanX>10){
       addHandle('n',
         {x:hL+CORNER,y:hT-EDGE/2,w:spanX,h:EDGE},
         {x:hL+CORNER+2,y:hT-GRIP/2,w:spanX-4,h:GRIP});
       addHandle('s',
         {x:hL+CORNER,y:hB-EDGE/2,w:spanX,h:EDGE},
         {x:hL+CORNER+2,y:hB-GRIP/2,w:spanX-4,h:GRIP});
     }

     // Vertical edges.
     const spanY=sideH-2*CORNER;
     if(spanY>10){
       addHandle('w',
         {x:hL-EDGE/2,y:hT+CORNER,w:EDGE,h:spanY},
         {x:hL-GRIP/2,y:hT+CORNER+2,w:GRIP,h:spanY-4});
       addHandle('e',
         {x:hR-EDGE/2,y:hT+CORNER,w:EDGE,h:spanY},
         {x:hR-GRIP/2,y:hT+CORNER+2,w:GRIP,h:spanY-4});
     }

     // Corners last: they sit on top of the edge strips.
     [['nw',hL,hT],['ne',hR,hT],['sw',hL,hB],['se',hR,hB]].forEach(([dir,px,py])=>{
       addHandle(dir,
         {x:px-CORNER/2,y:py-CORNER/2,w:CORNER,h:CORNER},
         null,
         {x:px,y:py});
     });

     // ---- One-click grow / shrink strips ----
     // Instead of floating balls, these are short rounded strips attached to
     // the real box wall. Their gradient starts in the wall colour and runs
     // to purple (grow) or red (remove), so the operation reads as part of
     // that exact edge.
     // Edge controls follow the zoom just enough to stay attached to their
     // wall, but are capped so they never become giant rails at 400%.
     const edgeUi=L.ui*Math.max(.72,Math.min(1.35,Math.sqrt(Math.max(.01,VIEWSTATE.z))));
     const railThick=Math.max(8*edgeUi,Math.min(13*edgeUi,Math.min(sideW,sideH)*.10));
     const mkArrow=(kind,dir,gx,gy,rot)=>{
       const grp=document.createElementNS(NS,'g');
       grp.setAttribute('class',kind==='grow'?'grow-btn':'grow-btn shrink-btn');
       grp.dataset.id=b.id;grp.dataset[kind]=dir;
       // A compact, centred rail leaves visual air at the wall ends. Its
       // length adapts to this exact box, so rails on short sides never
       // collide with the rails on the neighbouring sides.
       const verticalEdge=dir==='e'||dir==='w';
       const edgeSpan=verticalEdge?sideH:sideW;
       const normalSpan=verticalEdge?sideW:sideH;
       const thick=railThick;
       const endClear=Math.min(12*edgeUi,edgeSpan*.20);
       const usable=edgeSpan-2*endClear;
       if(usable<16*edgeUi||normalSpan<3*thick)return;
       const length=Math.min(56*edgeUi,usable,Math.max(16*edgeUi,usable*.44));
       const stripX=gx-(verticalEdge?thick:length)/2,stripY=gy-(verticalEdge?length:thick)/2;
       const stripW=verticalEdge?thick:length,stripH=verticalEdge?length:thick;
       const hit=document.createElementNS(NS,'rect');
       hit.setAttribute('x',stripX-5*edgeUi);hit.setAttribute('y',stripY-5*edgeUi);
       hit.setAttribute('width',stripW+10*edgeUi);hit.setAttribute('height',stripH+10*edgeUi);
       hit.setAttribute('rx',Math.max(stripW,stripH)/2);
       hit.setAttribute('class','grow-hit');
       grp.appendChild(hit);
       const defs=document.createElementNS(NS,'defs');
       const grad=document.createElementNS(NS,'linearGradient');
       const gradId='edge-strip-'+b.id+'-'+kind+'-'+dir;
       grad.setAttribute('id',gradId);grad.setAttribute('gradientUnits','userSpaceOnUse');
       const near=dir==='e'?[stripX,gy]:dir==='w'?[stripX+stripW,gy]:dir==='s'?[gx,stripY]:[gx,stripY+stripH];
       const far=dir==='e'?[stripX+stripW,gy]:dir==='w'?[stripX,gy]:dir==='s'?[gx,stripY+stripH]:[gx,stripY];
       grad.setAttribute('x1',near[0]);grad.setAttribute('y1',near[1]);
       grad.setAttribute('x2',far[0]);grad.setAttribute('y2',far[1]);
       const start=document.createElementNS(NS,'stop');start.setAttribute('offset','0%');start.setAttribute('stop-color',tint.stroke);
       const end=document.createElementNS(NS,'stop');end.setAttribute('offset','100%');end.setAttribute('stop-color',kind==='grow'?'#7b61ff':'#d84357');
       grad.appendChild(start);grad.appendChild(end);defs.appendChild(grad);grp.appendChild(defs);
       const strip=document.createElementNS(NS,'rect');
       strip.setAttribute('x',stripX);strip.setAttribute('y',stripY);
       strip.setAttribute('width',stripW);strip.setAttribute('height',stripH);
       strip.setAttribute('rx',Math.min(stripW,stripH)/2);strip.setAttribute('class','edge-strip');
       strip.setAttribute('fill','url(#'+gradId+')');strip.setAttribute('stroke',tint.stroke);
       grp.appendChild(strip);
       const a=document.createElementNS(NS,'path');
       a.setAttribute('d','M -2.5 -3.5 L 1.5 0 L -2.5 3.5');
       a.setAttribute('transform',`translate(${gx} ${gy}) rotate(${rot}) scale(${edgeUi})`);
       a.setAttribute('class','grow-arrow');
       grp.appendChild(a);
       const tt=document.createElementNS(NS,'title');
       tt.textContent=t(kind==='grow'?'box.grow':'box.shrink');
       grp.appendChild(tt);
       selUI.appendChild(grp);
     };
     // A grow is a complete new row/column. Draw that real group of cells as
     // one action target instead of a floating strip; 2×3 therefore gets a
     // 3-cell-tall target on its left/right side and a 2-cell-wide one above
     // and below.
     // Grow / shrink controls are compact circular handles centred on the
     // actual outer wall.  The neighbouring cell stays completely free for
     // the normal green "add box" action, so the resize control can never
     // steal that click target.
     const mkEdgeWallAction=(kind,dir)=>{
       const grp=document.createElementNS(NS,'g');
       grp.setAttribute('class','grow-btn edge-wall-action edge-wall-'+kind);
       grp.dataset.id=b.id;grp.dataset[kind]=dir;grp.dataset.dir=dir;
       // Grow (+) sits just OUTSIDE the selected box wall. Shrink (-) is
       // handled separately and sits just INSIDE the same wall, leaving a
       // visible gap between the two controls instead of stacking them.
       const wallCtrlGap=Math.max(10*L.ui, Math.min(16*L.ui, wallPx(L)*2.0));
       let gx,gy;
       if(dir==='e'){gx=hR+wallCtrlGap;gy=cy;}
       else if(dir==='w'){gx=hL-wallCtrlGap;gy=cy;}
       else if(dir==='s'){gx=cx;gy=hB+wallCtrlGap;}
       else {gx=cx;gy=hT-wallCtrlGap;}

       const hit=document.createElementNS(NS,'circle');
       hit.setAttribute('cx',gx);hit.setAttribute('cy',gy);
       hit.setAttribute('r',18*L.ui);
       hit.setAttribute('class','grow-hit');grp.appendChild(hit);

       const dot=document.createElementNS(NS,'circle');
       dot.setAttribute('cx',gx);dot.setAttribute('cy',gy);
       dot.setAttribute('r',9.5*L.ui);
       dot.setAttribute('class','edge-wall-dot');grp.appendChild(dot);

       const arm=4.2*L.ui;
       const sign=document.createElementNS(NS,'path');
       sign.setAttribute('d',kind==='grow'
         ? 'M '+(gx-arm)+' '+gy+' H '+(gx+arm)+' M '+gx+' '+(gy-arm)+' V '+(gy+arm)
         : 'M '+(gx-arm)+' '+gy+' H '+(gx+arm));
       sign.setAttribute('class','edge-wall-sign');grp.appendChild(sign);

       const tt=document.createElementNS(NS,'title');
       tt.textContent=t(kind==='grow'?'box.grow':'box.shrink');grp.appendChild(tt);
       selUI.appendChild(grp);
     };
     // Shrinking must not cover divider handles. It is a small red minus
     // attached directly to the outer wall whose row/column will be removed.
     const mkEdgeShrinkIcon=dir=>{
       // Shrink (-) stays just INSIDE the selected box wall. This is the
       // opposite side of the wall from the grow (+), with the same gap.
       const wallCtrlGap=Math.max(10*L.ui, Math.min(16*L.ui, wallPx(L)*2.0));
       // Shrink (-) is slightly farther inside the box than the previous
       // symmetric gap, leaving a small but visible separation from the wall.
       const shrinkCtrlGap=wallCtrlGap + 4*L.ui;
       const gx=dir==='e'?hR-shrinkCtrlGap:dir==='w'?hL+shrinkCtrlGap:cx;
       const gy=dir==='s'?hB-shrinkCtrlGap:dir==='n'?hT+shrinkCtrlGap:cy;
       const grp=document.createElementNS(NS,'g');
       grp.setAttribute('class','grow-btn edge-shrink-icon');
       grp.dataset.id=b.id;grp.dataset.shrink=dir;grp.dataset.dir=dir;
       const hit=document.createElementNS(NS,'circle');
       hit.setAttribute('cx',gx);hit.setAttribute('cy',gy);hit.setAttribute('r',15*L.ui);
       hit.setAttribute('class','grow-hit');grp.appendChild(hit);
       const dot=document.createElementNS(NS,'circle');
       dot.setAttribute('cx',gx);dot.setAttribute('cy',gy);dot.setAttribute('r',9*L.ui);
       dot.setAttribute('class','edge-shrink-dot');grp.appendChild(dot);
       const arm=4*L.ui,sign=document.createElementNS(NS,'path');
       sign.setAttribute('d','M '+(gx-arm)+' '+gy+' H '+(gx+arm));
       sign.setAttribute('class','edge-shrink-sign');grp.appendChild(sign);
       const tt=document.createElementNS(NS,'title');tt.textContent=t('box.shrink');grp.appendChild(tt);
       selUI.appendChild(grp);
     };
     // The background's ordinary green + remains available for creating a
     // separate box; these coloured zones are only for resizing this one.

     if(!shapeEditOn()){
     {
       [
         ['e',{...b,w:b.w+1}],['w',{...b,x:b.x-1,w:b.w+1}],
         ['s',{...b,h:b.h+1}],['n',{...b,y:b.y-1,h:b.h+1}],
       ].forEach(([dir,cand])=>{
         if(!fits(cand,b.id)||boxTooBig(cand.w,cand.h))return;
         mkEdgeWallAction('grow',dir);
       });
       // Red zones mark the complete existing edge that would be removed.
       if(b.w===1&&b.h===1){
         mkBoxRemoveIcon();
       }else{
         [
           ['e',b.w>1],['w',b.w>1],['s',b.h>1],['n',b.h>1],
         ].forEach(([dir,ok])=>{
           if(!ok)return;
           mkEdgeShrinkIcon(dir);
         });
       }
     }
     }
     }

     // L/T/free shapes use a deliberately different editor: every owned cell
     // shows a compact red − and every valid neighbouring cell a compact
     // purple +. The selected-cell grid and dimmed neighbours make these
     // affordances readable without covering the actual layout.
     if(shapeEditOn()){
       const ownCells=cellsOf(b);
       const own=new Set(ownCells.map(c=>c.x+','+c.y));
       const blocked=occupied(b.id);
       const mkCellMod=(mode,x,y,addDir=null)=>{
         let cx2=L.ox+x*L.stepX+L.cw/2,cy2=L.oy+y*L.stepY+L.ch/2;
         let grpDir=addDir||'center';
         if(mode==='add' && addDir){
           // The candidate cell may touch the selected shape on more than one
           // side.  Keep the exact shared-wall geometry for each direction;
           // CSS is only allowed to apply a small visual nudge afterwards.
           if(addDir==='e') cx2=L.ox+x*L.stepX;
           else if(addDir==='w') cx2=L.ox+(x+1)*L.stepX;
           else if(addDir==='n') cy2=L.oy+(y+1)*L.stepY;
           else if(addDir==='s') cy2=L.oy+y*L.stepY;
         }
         const grp=document.createElementNS(NS,'g');
         grp.setAttribute('class','cellmod-btn cellmod-'+mode);
         grp.dataset.id=b.id;grp.dataset.mode=mode;grp.dataset.cell=x+','+y;grp.dataset.dir=grpDir;
         // On hover, tint the exact delete target red so Free mode never
         // makes the user guess which square the minus will remove.
         if(mode==='remove'){
           const mark=document.createElementNS(NS,'rect');
           mark.setAttribute('x',L.ox+x*L.stepX);mark.setAttribute('y',L.oy+y*L.stepY);
           mark.setAttribute('width',L.cw);mark.setAttribute('height',L.ch);
           mark.setAttribute('class','cell-rm-mark');grp.appendChild(mark);
         }
         const hit=document.createElementNS(NS,'circle');
         hit.setAttribute('cx',cx2);hit.setAttribute('cy',cy2);hit.setAttribute('r',15*L.ui);
         hit.setAttribute('class','grow-hit');grp.appendChild(hit);
         const dot=document.createElementNS(NS,'circle');
         dot.setAttribute('cx',cx2);dot.setAttribute('cy',cy2);dot.setAttribute('r',9*L.ui);
         dot.setAttribute('class','cellmod-dot');grp.appendChild(dot);
         const sign=document.createElementNS(NS,'path'),arm=4*L.ui;
         sign.setAttribute('d',mode==='add'
           ? 'M '+(cx2-arm)+' '+cy2+' H '+(cx2+arm)+' M '+cx2+' '+(cy2-arm)+' V '+(cy2+arm)
           : 'M '+(cx2-arm)+' '+cy2+' H '+(cx2+arm));
         sign.setAttribute('class','cellmod-sign');grp.appendChild(sign);
         const title=document.createElementNS(NS,'title');
         title.textContent=t(mode==='add'?'box.addcell':'box.rmcell');grp.appendChild(title);
         selUI.appendChild(grp);
       };
       // A one-cell free shape cannot shrink any further. Give it the same
       // direct remove-minus as a 1×1 rectangular box instead of hiding the
       // only delete affordance.
       if(ownCells.length===1){
         mkBoxRemoveIcon();
       }else{
         ownCells.forEach(c=>mkCellMod('remove',c.x,c.y));
       }
       const offered=new Set(),gNow=grid();
       ownCells.forEach(c=>[[1,0],[-1,0],[0,1],[0,-1]].forEach(([dx,dy])=>{
         const x=c.x+dx,y=c.y+dy,key=x+','+y;
         if(x<0||y<0||x>=gNow.c||y>=gNow.r||own.has(key)||blocked[y][x])return;
         // One free candidate cell can be adjacent to the box on 2–3 sides.
         // In that case render one + for each valid shared wall instead of
         // collapsing them into a single control.
         const dir=dx===1?'e':dx===-1?'w':dy===-1?'n':'s';
         const controlKey=key+'|'+dir;
         if(offered.has(controlKey))return;
         offered.add(controlKey);
         mkCellMod('add',x,y,dir);
       }));
     }

     /*
      * A handle on every line inside the box where a divider could go: click
      * it to put one in, click it again to take it out. Signed-in only.
      * Dividers have square internal corners and connect to the box wall, while
      * the outer box corners may remain rounded.
      */
     if(wallModeOn()){
       const inBox=new Set(cellsOf(b).map(c=>c.x+','+c.y));
       const owns=(x,y)=>inBox.has(x+','+y);
       const on=new Set(Array.isArray(b.walls)?b.walls:[]);
       const halfOn=new Set(Array.isArray(b.halfWalls)?b.halfWalls:[]);
       const mkWall=(k,x1,y1,x2,y2)=>{
         const active=on.has(k);
         const grp=document.createElementNS(NS,'g');
         grp.setAttribute('class','wall-btn'+(active?' on':''));
         grp.dataset.id=b.id;grp.dataset.wall=k;

         const hit=document.createElementNS(NS,'line');
         hit.setAttribute('x1',x1);hit.setAttribute('y1',y1);
         hit.setAttribute('x2',x2);hit.setAttribute('y2',y2);
         hit.setAttribute('class','wall-hit');
         hit.setAttribute('stroke-width',Math.max(16,20*L.ui));
         grp.appendChild(hit);

         const ln=document.createElementNS(NS,'line');
         ln.setAttribute('x1',x1);ln.setAttribute('y1',y1);
         ln.setAttribute('x2',x2);ln.setAttribute('y2',y2);
         ln.setAttribute('class','wall-line');
         grp.appendChild(ln);

         const mx=(x1+x2)/2,my=(y1+y2)/2;
         const c=document.createElementNS(NS,'circle');
         c.setAttribute('cx',mx);c.setAttribute('cy',my);c.setAttribute('r',7.5*L.ui);
         c.setAttribute('class','wall-dot');
         grp.appendChild(c);
         const arm=4*L.ui;
         const sign=document.createElementNS(NS,'path');
         sign.setAttribute('d',active
           ? 'M '+(mx-arm)+' '+my+' H '+(mx+arm)
           : 'M '+(mx-arm)+' '+my+' H '+(mx+arm)+' M '+mx+' '+(my-arm)+' V '+(my+arm));
         sign.setAttribute('class','wall-sign');
         grp.appendChild(sign);

         const tt=document.createElementNS(NS,'title');
         tt.textContent=t(active?'box.rmwall':'box.addwall');
         grp.appendChild(tt);
         selUI.appendChild(grp);
       };
       if(WALLGRID===1)cellsOf(b).forEach(c=>{
         const cellL=L.ox+c.x*L.stepX, cellT=L.oy+c.y*L.stepY;
         if(owns(c.x+1,c.y)){
           const x=cellL+L.cw+L.gap/2;
           mkWall(wallKey('v',c.x,c.y),x,cellT+4,x,cellT+L.ch-4);
         }
         if(owns(c.x,c.y+1)){
           const y=cellT+L.ch+L.gap/2;
           mkWall(wallKey('h',c.x,c.y),cellL+4,y,cellL+L.cw-4,y);
         }
       });

       // The fine grid has a line half way through every original cell. Each
       // control is a complete cross-box wall, so its ends always meet the
       // outer shell and a 3 x 3 cannot create dangling fragments.
       const mkHalfWall=(type,hx,hy,x1,y1,x2,y2)=>{
         const active=halfOn.has(halfWallKey(type,hx,hy));
         const grp=document.createElementNS(NS,'g');
         grp.setAttribute('class','wall-btn midwall-btn'+(active?' on':''));
         grp.dataset.id=b.id;grp.dataset.halfwall=type+':'+hx+','+hy;
         const hit=document.createElementNS(NS,'line');
         hit.setAttribute('x1',x1);hit.setAttribute('y1',y1);hit.setAttribute('x2',x2);hit.setAttribute('y2',y2);
         hit.setAttribute('class','wall-hit');hit.setAttribute('stroke-width',Math.max(18,22*L.ui));grp.appendChild(hit);
         const ln=document.createElementNS(NS,'line');
         ln.setAttribute('x1',x1);ln.setAttribute('y1',y1);ln.setAttribute('x2',x2);ln.setAttribute('y2',y2);
         ln.setAttribute('class','wall-line midwall-line');grp.appendChild(ln);
         const mx=(x1+x2)/2,my=(y1+y2)/2,arm=4*L.ui;
         const dot=document.createElementNS(NS,'circle');dot.setAttribute('cx',mx);dot.setAttribute('cy',my);dot.setAttribute('r',8*L.ui);dot.setAttribute('class','wall-dot');grp.appendChild(dot);
         const sign=document.createElementNS(NS,'path');
         sign.setAttribute('d',active?'M '+(mx-arm)+' '+my+' H '+(mx+arm):'M '+(mx-arm)+' '+my+' H '+(mx+arm)+' M '+mx+' '+(my-arm)+' V '+(my+arm));
         sign.setAttribute('class','wall-sign');grp.appendChild(sign);
         const tt=document.createElementNS(NS,'title');tt.textContent=t(active?'box.rmwall':'box.addwall');grp.appendChild(tt);
         selUI.appendChild(grp);
       };
       if(WALLGRID===2)for(let hx=1;hx<b.w*2;hx++)for(let hy=0;hy<b.h*2;hy++){
         const x=hL+sideW*hx/(b.w*2), y1=hT+sideH*hy/(b.h*2), y2=hT+sideH*(hy+1)/(b.h*2);
         mkHalfWall('v',hx,hy,x,y1+3,x,y2-3);
       }
       if(WALLGRID===2)for(let hy=1;hy<b.h*2;hy++)for(let hx=0;hx<b.w*2;hx++){
         const y=hT+sideH*hy/(b.h*2), x1=hL+sideW*hx/(b.w*2), x2=hL+sideW*(hx+1)/(b.w*2);
         mkHalfWall('h',hx,hy,x1+3,y,x2-3,y);
       }
     }
   }

   // Keep grow/shrink controls visually above divider guide lines.
   // Divider controls are rendered after the resize arrows, so their
   // dashed guide line could otherwise paint over the red shrink button.
   selUI.querySelectorAll('.grow-btn').forEach(el => selUI.appendChild(el));
 });

 svg.appendChild(selUI);

 const total=g.c*g.r,used=boxes.reduce((s,b)=>s+cellsOf(b).length,0);
 $('boxCount').textContent=boxes.length;
 $('used').textContent=Math.round(used/total*100)+'%';
 $('free').textContent=Math.max(0,total-used);
 if(typeof updateStatusBar==='function') updateStatusBar(total,used);
 if(typeof savePrefsDebounced==='function') savePrefsDebounced();
 hasTooMany=MAXBOXES>0&&boxes.length>MAXBOXES;
 // A divider hanging in mid-air is a fault of the layout like any other, so
 // it belongs in the same verdict: the status line, the Fix button and the
 // marked rail icon, not only in a toast that has already faded.
 const wallsBad=dividersOn()&&anyWallsInvalid();
 /*
  * The floor plus a millimetre. The server refuses a box that is not
  * taller than its own floor; catching it here means the answer arrives
  * while the number is being typed instead of after an export is spent.
  */
 const minHeight=getMM('bottom')+1;
 const tooShort=getMM('dh')<minHeight-1e-9;
 const valid=used<=total&&boxes.every(b=>fits(b,b.id))&&!hasOversize&&!hasTooMany&&!wallsBad&&!tooShort;
 $('status').textContent=valid?t('status.ready'):t('status.invalid');
 $('status').className=valid?'ok':'bad';
 // Fix is an error-recovery action. A healthy Inspector stays clean and the
 // hidden button is also removed from keyboard focus.
 //
 // Everything else Fix repairs is a property of the boxes, so an empty drawer
 // has nothing for it to do - except a height that has fallen under its own
 // floor, which is a setting and is wrong with or without a box in the
 // drawer. Leaving the button hidden there meant reading what was wrong and
 // being offered nothing to do about it.
 const needsFix=!valid&&(boxes.length>0||tooShort);
 if($('fixBtn')) { $('fixBtn').hidden=!needsFix; $('fixBtn').disabled=!needsFix; }
 // Flag the rail icon that opens the Fix panel, so a problem shows even when
 // every popover is closed.
 const navInsp=document.querySelector('.nav-item[data-flyout="inspector"]');
 const invalidNow = !valid && (boxes.length>0||tooShort);
 if(navInsp) navInsp.classList.toggle('has-error', invalidNow);
 if($('inspectorError')){
   let emsg='';
   if(tooShort) emsg=t('err.tooshort',formatUnit(minHeight)+' '+currentUnit(),formatUnit(getMM('bottom'))+' '+currentUnit());
   else if(hasOversize) emsg=t('err.oversize');
   else if(hasTooMany) emsg=t('err.toomany');
   else if(wallsBad) emsg=t('err.wallfloats');
   else if(invalidNow) emsg=t('err.overflow');
   $('inspectorError').textContent=emsg;
   $('inspectorError').hidden=!emsg;
 }
 $('sizeBadge').textContent=`${formatUnit(getMM('dw'))} × ${formatUnit(getMM('dd'))} × ${formatUnit(getMM('dh'))} ${currentUnit()}`;
 if($('delBtn')) $('delBtn').disabled=selected===null;
 // The shared-design dialog shows this board on its left and says whether
 // taking the other one costs anything. Both follow the board.
 const shareBd=$('sharePreviewBackdrop');
 if(shareBd&&!shareBd.hidden&&typeof updateSharePreviewWarning==='function') updateSharePreviewWarning();

 // The bubble by the selected box: cells, real size, clone and delete. The
 // drawer size keeps its corner either way - the two no longer share a row,
 // so one no longer has to make way for the other.
 const selB=selected!==null?boxes.find(x=>x.id===selected):null;
 if($('selTools')){
   if(selB){
     const s=physSize(selB.w,selB.h);
     $('selInfo').textContent=isPoly(selB)
       ?`${t('box.cellcount',cellsOf(selB).length)} · ~ ${formatUnit(s.pW)} × ${formatUnit(s.pD)} ${currentUnit()}`
       :`${selB.w} × ${selB.h} · ${formatUnit(s.pW)} × ${formatUnit(s.pD)} ${currentUnit()}`;
     $('selTools').hidden=false;
   }else{
     $('selTools').hidden=true;
   }
   placeSelBubble(selB);
 }

 if(selected){
   let b=boxes.find(x=>x.id===selected);
   if(b&&$('bw')){$('bw').value=b.w;$('bd').value=b.h}
 }

 // Whether the note under the radius field applies depends on the boxes, so
 // it is settled here rather than only when the number changes.

 if(typeof paintFreeModeSwitch==='function') paintFreeModeSwitch();

 // Every change to the layout ends here, so this is where a step is noticed.
 historyTick();
}

function addBoxAt(x,y,w,h){
 const b={id:nextId++,x,y,w,h};
 if(!fits(b))return false;
 if(boxTooBig(w,h))return false;
 boxes.push(b);
 return true;
}

function randInt(min,max){
 return Math.floor(Math.random()*(max-min+1))+min;
}

function shuffle(arr){
 for(let i=arr.length-1;i>0;i--){
   const j=Math.floor(Math.random()*(i+1));
   [arr[i],arr[j]]=[arr[j],arr[i]];
 }
 return arr;
}



/**
 * Largest rectangle that fits at a free cell, preferring area.
 *
 * Capped by the requested maximum but not by the minimum: a hole one cell
 * wide can only take a one-cell box, and refusing to fill it would leave the
 * layout with holes at 100%. How many had to go under the minimum is
 * reported back so the setting does not appear to have been ignored.
 */

function initRandomOptions(){
 if(!$('randomMinW'))return;
 const g=grid();
 [
   ['randomMinW',g.c],
   ['randomMaxW',g.c],
   ['randomMinD',g.r],
   ['randomMaxD',g.r]
 ].forEach(([id,max])=>{
   options($(id),max);
 });
 $('randomMinW').value=1;
 $('randomMaxW').value=Math.min(3,g.c);
 $('randomMinD').value=1;
 $('randomMaxD').value=Math.min(3,g.r);
}
/*
 * FREE MODE for the generator: turn some of the rectangles it just laid out
 * into L and T pieces.
 *
 * Done by merging, not by drawing: two boxes that touch along an edge become
 * one box holding both sets of cells. A merge is only interesting when the
 * result is NOT a rectangle again - that is precisely what makes an L, a T or
 * an S - so unions that fill their own bounding box are skipped.
 *
 * Every merge is checked the same way a hand-drawn shape is: one connected
 * piece, no enclosed hole a printer could never clear, and still inside the
 * print area. Anything that fails is left as two separate boxes, so the worst
 * case of this pass is simply that nothing changes.
 */
function freeModeMerge(strength,limW,limD){
 const key=c=>c.x+','+c.y;
 /*
  * Shapes are made once and then left alone.
  *
  * Without this a shape that has just been merged is the biggest box on
  * the board, so it wins the next draw as well, and the one after that:
  * a 5x5 drawer collapsed into two enormous blobs. A piece that has been
  * shaped is finished, and there is a ceiling on how big one may get.
  */
 const shaped=new Set();
 const capW=Math.max(2,limW||3), capD=Math.max(2,limD||3);
 const capCells=Math.max(3,Math.min(6,capW*capD-1));
 const isRect=cells=>{
   const xs=cells.map(c=>c.x), ys=cells.map(c=>c.y);
   const w=Math.max(...xs)-Math.min(...xs)+1;
   const h=Math.max(...ys)-Math.min(...ys)+1;
   return cells.length===w*h;
 };
 const usable=cells=>{
   const xs=cells.map(c=>c.x), ys=cells.map(c=>c.y);
   const w=Math.max(...xs)-Math.min(...xs)+1;
   const h=Math.max(...ys)-Math.min(...ys)+1;
   if(w>capW||h>capD||cells.length>capCells)return false;
   return !boxTooBig(w,h)&&cellsConnected(cells)&&!cellsHaveHole(cells);
 };
 const neighboursOf=b=>{
   const set=new Set(cellsOf(b).map(key));
   return boxes.filter(o=>o.id!==b.id&&cellsOf(o).some(c=>
     set.has((c.x+1)+','+c.y)||set.has((c.x-1)+','+c.y)||
     set.has(c.x+','+(c.y+1))||set.has(c.x+','+(c.y-1))));
 };
 const cellsOfAll=list=>{
   const out=[];
   list.forEach(b=>cellsOf(b).forEach(c=>out.push({x:c.x,y:c.y})));
   return out;
 };

 let tries=Math.max(1,Math.round(boxes.length*strength));
 let made=0;
 let guard=boxes.length*8+40;

 while(tries>0 && guard-- > 0 && boxes.length>1){
   tries--;
   const a=boxes[Math.floor(Math.random()*boxes.length)];
   if(shaped.has(a.id))continue;
   const near=neighboursOf(a).filter(b=>!shaped.has(b.id));
   if(!near.length)continue;
   // Shuffled, so the same drawer comes out differently every run.
   for(let i=near.length-1;i>0;i--){ const j=Math.floor(Math.random()*(i+1)); [near[i],near[j]]=[near[j],near[i]]; }

   /*
    * One neighbour first, two if that only made a bigger rectangle.
    *
    * Two 1x1 boxes merge into a 1x2 - still a rectangle, and a drawer full
    * of single cells (which is what the generator makes at 100 % fill)
    * would never produce a single L that way. Three cells in the right
    * arrangement always can, so the pass takes a second neighbour when the
    * first one is not enough.
    */
   let chosen=null;
   for(const b of near){
     const two=cellsOfAll([a,b]);
     if(!isRect(two)&&usable(two)){ chosen=[b]; break; }
   }
   if(!chosen){
     outer:
     for(const b of near){
       for(const c of near){
         if(c.id===b.id)continue;
         const three=cellsOfAll([a,b,c]);
         if(!isRect(three)&&usable(three)){ chosen=[b,c]; break outer; }
       }
     }
   }
   if(!chosen)continue;

   /*
    * A shape keeps only the dividers that sit on a cell boundary.
    *
    * The middle divider and the fine 2:1 ones are defined against the box's
    * bounding rectangle, and an L's bounding rectangle covers cells the box
    * does not own - so after a merge those walls were drawn straight out
    * into the empty part of the rectangle. They are dropped here rather
    * than reinterpreted: there is no sensible "middle" of an L.
    */
   delete a.midWalls;
   delete a.halfWalls;
   a.cells=cellsOfAll([a].concat(chosen));
   syncPolyBounds(a);
   const gone=new Set(chosen.map(b=>b.id));
   boxes=boxes.filter(b=>!gone.has(b.id));
   shaped.add(a.id);
   made++;
 }
 return made;
}
function randomLayout(){
 if(!$('randomMinW'))return;
 const g=grid();
 let minW=+$('randomMinW').value;
 let maxW=+$('randomMaxW').value;
 let minD=+$('randomMinD').value;
 let maxD=+$('randomMaxD').value;
 const target=+$('randomFill').value;
 const variation=$('randomVariation').value;
 minW=Math.max(1,Math.min(g.c,minW));
 maxW=Math.max(minW,Math.min(g.c,maxW));
 minD=Math.max(1,Math.min(g.r,minD));
 maxD=Math.max(minD,Math.min(g.r,maxD));
 boxes=[];
 selected=null;
 const maxAttempts=Math.max(250,g.c*g.r*80);
 let attempts=0;
 let filled=0;
 const targetCells=Math.max(1,Math.floor(g.c*g.r*target));
 while(attempts<maxAttempts && filled<targetCells){
   attempts++;
   const free=[];
   const occ=occupied();
   for(let y=0;y<g.r;y++){
     for(let x=0;x<g.c;x++){
       if(!occ[y][x])free.push({x,y});
     }
   }
   if(!free.length)break;
   // A random free cell every time - even at 100% fill, where the start used
   // to be fixed and made every layout identical. Leftover gaps are still
   // filled at the end, so the result stays full but is different each run.
   const start=free[Math.floor(Math.random()*free.length)];
   let candidates=[];
   for(let w=minW;w<=maxW;w++){
     for(let h=minD;h<=maxD;h++){
       if(start.x+w<=g.c && start.y+h<=g.r){
         const test={x:start.x,y:start.y,w,h};
         if(!fits(test))continue;
         const restX=g.c-(start.x+w);
         const restY=g.r-(start.y+h);
         test.awkward=(restX>0&&restX<minW?1:0)+(restY>0&&restY<minD?1:0);
         candidates.push(test);
       }
     }
   }
   if(!candidates.length)continue;
   candidates=shuffle(candidates);
   candidates.sort((a,b)=>{
     if(target>=1 && a.awkward!==b.awkward) return a.awkward-b.awkward;
     const aa=a.w*a.h, bb=b.w*b.h;
     if(variation==='large')return bb-aa;
     if(variation==='small')return aa-bb;
     if(variation==='balanced'){
       const ideal=Math.max(2,Math.round((minW+maxW)*(minD+maxD)/4));
       return Math.abs(aa-ideal)-Math.abs(bb-ideal);
     }
     return Math.random()-0.5;
   });
   // Weighted-random pick: the sort still leans toward the chosen variation,
   // but any candidate can win, so runs differ and small boxes get a real
   // chance rather than always losing to the "ideal" one.
   let pick=0;
   if(candidates.length>1){
     const span=variation==='mixed'?candidates.length:Math.min(candidates.length,4);
     pick=Math.floor(Math.pow(Math.random(),1.7)*span);
   }
   const b=candidates[pick];
   if(addBoxAt(b.x,b.y,b.w,b.h)){
     filled+=b.w*b.h;
   }
 }
 let undersized=0;
 const fillHoles=(limit)=>{
   // One box per free cell at worst, so this can never spin.
   let safety=g.c*g.r+1;
   while(safety-- > 0 && (limit===null || filled<limit)){
     const p=firstFree(1,1);
     if(!p)break;
     // The maximum is a ceiling and stays one; the minimum does not apply
     // here, so a leftover gap is taken with whatever fits it, down to a
     // single cell.
     const b=bestFillAt(p,g,maxW,maxD);
     if(!b)break;
     // Counting a box that was refused as filled area is how the loop used
     // to convince itself the drawer was full while a hole was still there.
     if(!addBoxAt(b.x,b.y,b.w,b.h))break;
     filled+=b.w*b.h;
     if(b.w<minW || b.h<minD) undersized++;
   }
 };
 fillHoles(target>=1 ? null : targetCells);

 /*
  * FREE MODE runs last, on a finished layout: merging two neighbours into
  * an L cannot leave a hole the way growing shapes as we go could, and the
  * fill level is already satisfied before a single piece changes shape.
  */
 /*
  * Not while wall mode is on. Dividers are defined against a rectangle,
  * and entering wall mode converts shapes back into rectangles anyway -
  * so generating them here would only produce something the next mode
  * switch throws away.
  */
 const freeBox=$('randomFree');
 if(freeBox&&freeBox.checked&&signedIn()&&!wallModeOn()){
   // The same ceiling the user set for ordinary boxes applies to the shapes.
   freeModeMerge(0.5,maxW,maxD);
 }


 selected=boxes.length?boxes[0].id:null;
 const seed=Math.floor(Math.random()*1000000);
 const note=$('randomSeed');
 if(note){
   note.textContent=t('seed')+' '+seed
     + (undersized ? ' - ' + t('random.undersized',undersized) : '');
 }
 draw();
}
/**
 * Where a copy of this box would go, or null when there is no room.
 *
 * Tried in reading order - right, below, left, above - so the copy lands
 * where the eye expects it. Only if none of those are free does it fall back
 * to scanning the grid, which keeps a clone next to its original whenever
 * that is possible at all.
 */
function cloneSpot(b){
  const g=grid();

  const near=[
    {x:b.x+b.w, y:b.y},
    {x:b.x,     y:b.y+b.h},
    {x:b.x-b.w, y:b.y},
    {x:b.x,     y:b.y-b.h},
  ];

  for(const p of near){
    if(p.x<0||p.y<0||p.x+b.w>g.c||p.y+b.h>g.r) continue;
    if(fits({x:p.x,y:p.y,w:b.w,h:b.h})) return p;
  }

  return firstFree(b.w,b.h);
}

/**
 * Biggest box that can go at this free cell.
 *
 * Bounded above by the generator's maximum and by the print bed, and not
 * bounded below at all: at 100% the point is to leave no hole, and a gap
 * one cell wide can only take a one-cell box. Going under the configured
 * minimum is reported afterwards, so the setting does not look ignored.
 */
function bestFillAt(p,g,maxW,maxD){
  let best=null;
  const wLimit=Math.min(maxW,g.c-p.x);
  const hLimit=Math.min(maxD,g.r-p.y);
  for(let w=wLimit;w>=1;w--){
    for(let h=hLimit;h>=1;h--){
      // A box the printer cannot make is no use: it used to be picked as
      // the best fit, refused on the way in, and picked again next time
      // round - which is how a drawer at 100% still ended up with holes.
      if(boxTooBig(w,h))continue;
      const test={x:p.x,y:p.y,w,h};
      if(fits(test) && (!best || w*h>best.w*best.h)) best=test;
    }
  }
  return best;
}
function addBox(w=1,h=1){let p=firstFree(w,h);if(!p)return false;let b={id:nextId++,x:p.x,y:p.y,w,h};boxes.push(b);selected=b.id;return true}
function mixed(){
  $('randomFill').value='1';
  $('randomVariation').value='mixed';
  randomLayout();
}
function autoFill(){while(addBox(1,1)){}draw()}

let hoverCell=null;
svg.addEventListener('pointermove',e=>{
 if(drag){
   e.preventDefault();
   let b=boxes.find(x=>x.id===drag.id);
   if(!b)return;

   let p=pte(e);
   let dx=Math.round((p.x-drag.sx)/drag.cw);
   let dy=Math.round((p.y-drag.sy)/drag.ch);
   let o=drag.orig,n={...o};


   if(drag.resize){
     const d=drag.resizeDir;
     // Trailing edges only change the size. Leading edges move the origin
     // too, and are clamped so they can never cross the opposite edge.
     if(d.includes('e')) n.w=Math.max(1,o.w+dx);
     if(d.includes('s')) n.h=Math.max(1,o.h+dy);
     if(d.includes('w')){
       n.x=Math.min(o.x+dx,o.x+o.w-1);
       n.w=o.x+o.w-n.x;
     }
     if(d.includes('n')){
       n.y=Math.min(o.y+dy,o.y+o.h-1);
       n.h=o.y+o.h-n.y;
     }
   }else{
     n.x=o.x+dx;n.y=o.y+dy;
   }

   // Only GROWING past the print area is refused, so an already-oversized box
   // can always be shrunk back. A note says why it stopped growing.
   const grows = n.w>b.w || n.h>b.h;
   const blockedByPrint = drag.resize && grows && fits(n,b.id) && boxTooBig(n.w,n.h);
   if(fits(n,b.id) && !blockedByPrint){
     // Moving takes the dividers along; resizing leaves them where they are
     // in the drawer and simply loses the ones outside the new box.
     if(!drag.resize) shiftWalls(b,n.x-b.x,n.y-b.y);
     Object.assign(b,n);
     sanitizeWalls(b);
   }
   if(blockedByPrint) showExportError(t('hint.printlimit'));
   draw();
   return;
 }

 const next=cellAt(pte(e),layout());
 if((hoverCell?.x!==next?.x)||(hoverCell?.y!==next?.y)){
   hoverCell=next;
   draw();
 }
});

svg.addEventListener('pointerleave',()=>{
 if(!drag&&hoverCell){hoverCell=null;draw()}
});

// Click a ghost "+" square to grow the grid on that side. Left/top also shift
// the boxes so they keep their place.
/*
 * Growing or shrinking the grid, animated.
 *
 * Every box is measured before the change and springs from its old rectangle
 * into the new one afterwards (the same FLIP the resize handles use), so the
 * whole drawer reads as stretching rather than snapping to a different
 * layout. The fresh row or column fades its cells in behind them.
 *
 * Boxes are measured BEFORE anything moves, because after the redraw the old
 * positions are gone - there is nothing left to animate from.
 */
function animateGridChange(apply){
 const reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 if(reduced){ apply(); return; }

 const L=layout();
 const before=new Map();
 boxes.forEach(b=>before.set(b.id,boxRect(b,L)));

 apply();

 // The cells are new elements every draw, so the class goes on the canvas
 // and the stylesheet does the rest.
 if(svg){
   svg.classList.remove('grid-changed');
   void svg.getBoundingClientRect();
   svg.classList.add('grid-changed');
   setTimeout(()=>svg.classList.remove('grid-changed'),520);
 }
 boxes.forEach(b=>{
   const was=before.get(b.id);
   if(was&&was.w>0&&was.h>0) animateBoxResize(b.id,was);
 });
}
function growGrid(side){
 const g=grid();
 const shiftAll=(dx,dy)=>boxes.forEach(b=>{
   b.x+=dx;b.y+=dy;
   if(isPoly(b))b.cells.forEach(c=>{c.x+=dx;c.y+=dy});
   shiftWalls(b,dx,dy);
 });
 if(side==='col-right'&&g.c<MAXCELLS){ animateGridChange(()=>{ $('cols').value=g.c+1; onGridChange(); }); }
 else if(side==='row-bottom'&&g.r<MAXCELLS){ animateGridChange(()=>{ $('rows').value=g.r+1; onGridChange(); }); }
 else if(side==='col-left'&&g.c<MAXCELLS){ animateGridChange(()=>{ shiftAll(1,0); $('cols').value=g.c+1; onGridChange(); }); }
 else if(side==='row-top'&&g.r<MAXCELLS){ animateGridChange(()=>{ shiftAll(0,1); $('rows').value=g.r+1; onGridChange(); }); }
}
// Remove the outermost column/row on a side, but only if it is empty.
function shrinkGrid(side){
 const g=grid();
 const isCol=side.indexOf('col')===0;
 const warn=()=>{ if(typeof showExportError==='function') showExportError(isCol?t('err.colnotempty'):t('err.rownotempty')); };
 if(side==='col-right'){
   if(g.c<=1)return;
   if(boxes.some(b=>b.x+b.w===g.c)){ warn(); return; }
   animateGridChange(()=>{ $('cols').value=g.c-1; onGridChange(); });
 }else if(side==='row-bottom'){
   if(g.r<=1)return;
   if(boxes.some(b=>b.y+b.h===g.r)){ warn(); return; }
   animateGridChange(()=>{ $('rows').value=g.r-1; onGridChange(); });
 }else if(side==='col-left'){
   if(g.c<=1)return;
   if(boxes.some(b=>b.x===0)){ warn(); return; }
   boxes.forEach(b=>{b.x-=1;if(isPoly(b))b.cells.forEach(c=>c.x-=1);shiftWalls(b,-1,0)});
   animateGridChange(()=>{ $('cols').value=g.c-1; onGridChange(); });
 }else if(side==='row-top'){
   if(g.r<=1)return;
   if(boxes.some(b=>b.y===0)){ warn(); return; }
   boxes.forEach(b=>{b.y-=1;if(isPoly(b))b.cells.forEach(c=>c.y-=1);shiftWalls(b,0,-1)});
   animateGridChange(()=>{ $('rows').value=g.r-1; onGridChange(); });
 }
}

// A ghost "+" grows the grid on a *double* click. Detected by hand so it works
// the same whether the browser fires a native dblclick on the SVG or not.
let _ghostClick={side:null,t:0};
/**
 * FLIP animation for a resized box: the freshly drawn rectangle starts as the
 * OLD shape (inverted transform) and springs into its real, new one. Reads as
 * the box itself stretching like water rather than snapping.
 */
function animateBoxResize(id,before){
  const el=svg.querySelector('.box[data-id="'+id+'"]');
  const b=boxes.find(x=>x.id===id);
  if(!el||!b)return;
  const after=boxRect(b,layout());
  if(after.w<1||after.h<1)return;
  el.style.transformBox='fill-box';
  el.style.transformOrigin='0 0';
  el.style.transition='none';
  el.style.transform='translate('+(before.x-after.x)+'px,'+(before.y-after.y)+'px) '
                    +'scale('+(before.w/after.w)+','+(before.h/after.h)+')';
  el.getBoundingClientRect();   // flush, so the transition below actually runs
  el.style.transition='transform .5s cubic-bezier(.3,1.45,.45,1)';
  el.style.transform='none';
  setTimeout(()=>{ el.style.transition=''; el.style.transform=''; },560);
}

svg.addEventListener('pointerdown',e=>{
 // Hand tool (or space held): the canvas moves, nothing gets selected,
 // resized or deleted by accident.
 if(typeof window.h3dPanMode==='function'&&window.h3dPanMode())return;
 const gside=e.target&&e.target.dataset?e.target.dataset.ghost:null;
 if(gside){
   // A single click adds or removes the row/column right away.
   e.preventDefault();
   if(gside.slice(-3)===':rm') shrinkGrid(gside.slice(0,-3)); else growGrid(gside);
   return;
 }
 e.preventDefault();

 // Divider handles: one click puts a wall on that line, another takes it off.
 const wallG=e.target.closest?e.target.closest('.wall-btn'):null;
 if(wallG){
   const b=boxes.find(x=>x.id===+wallG.dataset.id);
   if(b&&wallModeOn()){
     if(wallG.dataset.halfwall){const [type,coords]=wallG.dataset.halfwall.split(':');const [x,y]=coords.split(',').map(Number);toggleHalfWall(b,type,x,y);}
     else if(wallG.dataset.midwall)toggleMidWall(b,wallG.dataset.midwall);
     else toggleWall(b,wallG.dataset.wall);
     draw();
     // Invalid divider ends are reported by Inspector, not as a toast after
     // each drawing click.
   }
   return;
 }

 // A 1×1 box uses its minus as a direct remove action. Keep this ahead of
 // the normal resize/cell handlers so it cannot be mistaken for a cell edit.
 const removeBoxG=e.target.closest?e.target.closest('.box-remove-icon'):null;
 if(removeBoxG&&removeBoxG.dataset.removeBox==='1'){
   const b=boxes.find(x=>x.id===+removeBoxG.dataset.id);
   if(b){
     selected=b.id;
     deleteSelected();
   }
   return;
 }

 // Cell +/- pills on the selected box: claim or give back ONE exact cell.
 // This is how a signed-in user sculpts an L, a T or any free shape.
 const cellG=e.target.closest?e.target.closest('.cellmod-btn'):null;
 if(cellG){
   if(!shapeEditOn())return;
   const b=boxes.find(x=>x.id===+cellG.dataset.id);
   if(b){
     const [gx2,gy2]=cellG.dataset.cell.split(',').map(Number);
     const before=boxRect(b,layout());
     if(cellG.dataset.mode==='add'){
       const cells=cellsOf(b).map(c=>({x:c.x,y:c.y}));
       cells.push({x:gx2,y:gy2});
       // Rings are allowed now, so a hole is fine; a corner joint just
       // needs the two cells beside it to stay free.
       b.cells=cells;syncPolyBounds(b);
       draw();
       animateBoxResize(b.id,before);
     }else{
       const cells=cellsOf(b).filter(c=>!(c.x===gx2&&c.y===gy2)).map(c=>({x:c.x,y:c.y}));
       if(!cells.length){draw();return;}
       // Cutting the shape in two (an L losing its corner) is allowed: the
       // pieces simply become boxes of their own.
       const comps=cellComponents(cells);
       // The dividers are on absolute lines, so each piece simply keeps the
       // ones between two of its own cells.
       const hadWalls=Array.isArray(b.walls)?b.walls.slice():null;
       b.cells=comps[0].map(c=>({x:c.x,y:c.y}));
       syncPolyBounds(b);
       for(let ci=1;ci<comps.length;ci++){
         const nb={id:nextId++,x:0,y:0,w:1,h:1,cells:comps[ci].map(c=>({x:c.x,y:c.y}))};
         if(hadWalls)nb.walls=hadWalls.slice();
         syncPolyBounds(nb);
         boxes.push(nb);
       }
       draw();
       animateBoxResize(b.id,before);
     }
   }
   return;
 }

 // Grow / shrink arrows on the selected box: one click, one cell.
 const growG=e.target.closest?e.target.closest('.grow-btn'):null;
 if(growG){
   const b=boxes.find(x=>x.id===+growG.dataset.id);
   if(b){
     const isShrink=!!growG.dataset.shrink;
     const dir=isShrink?growG.dataset.shrink:growG.dataset.grow;
     let cand;
     if(isShrink){
       cand=dir==='e'?{...b,w:b.w-1}
           :dir==='w'?{...b,x:b.x+1,w:b.w-1}
           :dir==='s'?{...b,h:b.h-1}
           :{...b,y:b.y+1,h:b.h-1};
       if(cand.w<1||cand.h<1)cand=null;
     }else{
       cand=dir==='e'?{...b,w:b.w+1}
           :dir==='w'?{...b,x:b.x-1,w:b.w+1}
           :dir==='s'?{...b,h:b.h+1}
           :{...b,y:b.y-1,h:b.h+1};
       if(!fits(cand,b.id)||boxTooBig(cand.w,cand.h))cand=null;
     }
     if(cand){
       // The effect IS the resize: remember where the box was, apply the
       // change, then let the redrawn rectangle stretch from the old shape
       // into the new one with a springy, liquid overshoot.
       const before=boxRect(b,layout());
       Object.assign(b,cand);
       // Shrinking with the one-click handles can remove cells that owned
       // existing dividers. Drop every divider that no longer lies between
       // two cells of the resized box immediately; otherwise the old wall
       // would remain visible in the freed space.
       sanitizeWalls(b);
       draw();
       animateBoxResize(b.id,before);
     }else{
       draw();
     }
   }
   return;
 }

 // Duplicate and delete used to be drawn inside the selected box; they live
 // in the panel above the drawing now (topClone / topDelete), where they do
 // not fight the resize handles for the same few pixels.

 // Empty cells are only added through the visible plus.
 if(e.target.dataset.add==='1'){
   const x=+e.target.dataset.x,y=+e.target.dataset.y;
   if(addBoxAt(x,y,1,1)){
     selected=boxes[boxes.length-1].id;
     hoverCell=null;draw();
   }
   return;
 }

 let id=e.target.dataset.id?+e.target.dataset.id:null;
 if(id===null){
   // Clicked past every box: nothing is selected any more. Every handle,
   // pill and button of a selection is dealt with above, so anything that
   // reaches here really is the empty drawer.
   if(selected!==null){ selected=null; draw(); }
   return;
 }

 let b=boxes.find(x=>x.id===id);
 if(!b)return;

 selected=id;
 const p=pte(e),L=layout();
 drag={
   id,
   resize:e.target.dataset.resize==='1',
   resizeDir:e.target.dataset.resizeDir||null,
   // Only where the box is and how big it is. A copy of the whole box would
   // carry its cells and its dividers along, and since every pointer move
   // writes this snapshot back, the dividers landed on their old lines again
   // and again - and the box, by then somewhere else, threw them away as
   // lines it does not own.
   sx:p.x,sy:p.y,cw:L.stepX,ch:L.stepY,orig:{x:b.x,y:b.y,w:b.w,h:b.h},
 };
 svg.setPointerCapture(e.pointerId);
 draw();
});

svg.addEventListener('pointerup',e=>{
 drag=null;
 try{svg.releasePointerCapture(e.pointerId)}catch{}
 draw();
});

svg.addEventListener('pointercancel',e=>{
 drag=null;
 try{svg.releasePointerCapture(e.pointerId)}catch{}
 draw();
});


if($('bw')){
  $('bw').onchange=()=>{let b=boxes.find(x=>x.id===selected);if(!b||isPoly(b))return;let n={...b,w:+$('bw').value};if(fits(n,b.id)&&(n.w<=b.w||!boxTooBig(n.w,n.h)))b.w=n.w;draw()}
  $('bd').onchange=()=>{let b=boxes.find(x=>x.id===selected);if(!b||isPoly(b))return;let n={...b,h:+$('bd').value};if(fits(n,b.id)&&(n.h<=b.h||!boxTooBig(n.w,n.h)))b.h=n.h;draw()}
}
function deleteSelected(){
  if(selected===null)return;
  const index=boxes.findIndex(b=>b.id===selected);
  if(index<0){selected=null;draw();return;}
  boxes.splice(index,1);
  // Select the next logical box so the editor remains ready for continued work.
  if(boxes.length){
    const next=Math.min(index,boxes.length-1);
    selected=boxes[next].id;
  }else{
    selected=null;
  }
  draw();
}
if($('delBtn')) $('delBtn').onclick=deleteSelected;

document.addEventListener('keydown',e=>{
  const tag=(e.target.tagName||'').toLowerCase();
  const editing=tag==='input'||tag==='select'||tag==='textarea'||e.target.isContentEditable;
  if(editing)return;
  if((e.key==='Delete'||e.key==='Backspace') && selected!==null){
    e.preventDefault();
    deleteSelected();
    return;
  }
  // Nudge the selected box a cell at a time with the arrow keys; the move is
  // only applied if the box still fits, so it never overlaps or leaves the grid.
  if(selected!==null && e.key.slice(0,5)==='Arrow'){
    const b=boxes.find(x=>x.id===selected);
    if(!b)return;
    const d={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]}[e.key];
    if(!d)return;
    e.preventDefault();
    if(isPoly(b)){
      const cells=cellsOf(b).map(c=>({x:c.x+d[0],y:c.y+d[1]}));
      if(fits({cells},b.id)){b.cells=cells;syncPolyBounds(b);draw();}
      return;
    }
    const n={...b,x:b.x+d[0],y:b.y+d[1]};
    if(fits(n,b.id)){Object.assign(b,n);draw();}
  }
});
// Clear layout lives as a quick icon by the canvas now, but the handler is
// shared so any leftover button keeps working too.
const clearLayout=async()=>{
  // Nothing to lose on an empty drawer, so skip straight to clearing it.
  if(boxes.length){
    const go=await confirmAction(t('layout.clearTitle'),t('layout.clearBody'),t('layout.clearOk'));
    if(!go)return;
  }
  boxes=[];selected=null;draw();
};
if($('newBtn')) $('newBtn').onclick=clearLayout;
if($('clearLayoutBtn')) $('clearLayoutBtn').onclick=clearLayout;
const autoBtn=$('autoBtn');
if(autoBtn) autoBtn.onclick=async()=>{
  // Filling with 1x1 boxes replaces whatever is on the canvas, so ask first -
  // same graphical confirm as Delete layout.
  if(boxes.length){
    const go=await confirmAction(t('layout.fillTitle'),t('layout.fillBody'),t('layout.fillOk'));
    if(!go)return;
    boxes=[];selected=null;
  }
  autoFill();
};

/**
 * Make an invalid layout valid without touching the drawer size: grow the
 * grid (up to the shop's limit) so every box fits where it sits, then add
 * rows/columns to shrink the cells until nothing is too big for the bed.
 */
function fixLayout(){
  // A box shorter than its own floor plus a millimetre. The height is what
  // gives, because the floor is a thickness someone chose on purpose while
  // the height usually got left behind when the floor grew.
  enforceMinHeight();
  // A divider with a free end: take it out. Fix is offered because of it, so
  // it had better be one of the things Fix does.
  boxes.forEach(b=>{
    let guard=50,k;
    while((k=danglingWall(b))&&guard-->0){ toggleWall(b,k); }
    guard=400;
    while((k=danglingFineWall(b))&&guard-->0){
      const m=/^([vh]):(\d+),(\d+)$/.exec(k);
      if(!m)break;
      toggleHalfWall(b,m[1],+m[2],+m[3]);
    }
  });
  // Too many boxes for what the shop allows: drop the extras (the most
  // recently added) down to the limit.
  if(MAXBOXES>0 && boxes.length>MAXBOXES){
    boxes=boxes.slice(0,MAXBOXES);
    if(selected!==null && !boxes.some(b=>b.id===selected)) selected=null;
  }
  let needC=+$('cols').value, needR=+$('rows').value;
  boxes.forEach(b=>{ needC=Math.max(needC,b.x+b.w); needR=Math.max(needR,b.y+b.h); });
  $('cols').value=Math.min(MAXCELLS,needC);
  $('rows').value=Math.min(MAXCELLS,needR);
  // The whole grid UI, not just the box pickers: the sliders and the
  // generator have to end up showing the grid that is actually on screen.
  syncGridUI();
  draw();

  let guard=40;
  while(hasOversize && guard-->0){
    let c=+$('cols').value, r=+$('rows').value;
    if(r<=c && r<MAXCELLS) r++; else if(c<MAXCELLS) c++; else break;
    $('cols').value=c; $('rows').value=r;
    syncGridUI();
    draw();
  }
}
if($('fixBtn')) $('fixBtn').onclick=fixLayout;

/** Duplicate the selected box into the nearest free spot. */
function cloneSelected(){
  const src=selected!==null?boxes.find(x=>x.id===selected):null;
  if(!src)return;
  if(isPoly(src)){
    // Copy the whole shape: shift every cell by the same offset, first to
    // the obvious neighbouring spots, then wherever it fits at all.
    const cells=cellsOf(src),g=grid();
    const tryAt=(dx,dy)=>{
      const moved=cells.map(c=>({x:c.x+dx,y:c.y+dy}));
      return fits({cells:moved})?moved:null;
    };
    let placed=null;
    for(const [dx,dy] of [[src.w,0],[0,src.h],[-src.w,0],[0,-src.h]]){
      placed=tryAt(dx,dy);
      if(placed)break;
    }
    if(!placed){
      outer:
      for(let dy=-src.y;dy<g.r-src.y;dy++){
        for(let dx=-src.x;dx<g.c-src.x;dx++){
          if(dx===0&&dy===0)continue;
          placed=tryAt(dx,dy);
          if(placed)break outer;
        }
      }
    }
    if(placed){
      const nb={id:nextId++,x:src.x,y:src.y,w:src.w,h:src.h,cells:placed};
      syncPolyBounds(nb);
      boxes.push(nb);
      selected=nb.id;
    }
    draw();
    return;
  }
  const spot=cloneSpot(src);
  if(spot&&addBoxAt(spot.x,spot.y,src.w,src.h)){
    const nb=boxes[boxes.length-1];
    if(src.walls)nb.walls=src.walls.slice();
    if(src.midWalls)nb.midWalls=src.midWalls.slice();
    if(src.halfWalls)nb.halfWalls=src.halfWalls.slice();
    selected=nb.id;
  }
  draw();
}
if($('topDelete')) $('topDelete').onclick=deleteSelected;
if($('topClone'))  $('topClone').onclick=cloneSelected;
if($('topDeselect')) $('topDeselect').onclick=()=>{ selected=null; draw(); };

// Undo / redo: the strip button and the keys everybody already presses.
if(HIST.on&&$('ctHist')){
  $('ctHist').hidden=false;
  $('undoBtn').onclick=()=>historyStep(true);
  $('redoBtn').onclick=()=>historyStep(false);
  document.addEventListener('keydown',e=>{
    if(!(e.ctrlKey||e.metaKey))return;
    // Not while typing a drawer size or a note.
    const tag=(e.target&&e.target.tagName||'').toLowerCase();
    if(tag==='input'||tag==='textarea'||tag==='select')return;
    const k=e.key.toLowerCase();
    if(k==='z'&&!e.shiftKey){e.preventDefault();historyStep(true);}
    else if(k==='y'||(k==='z'&&e.shiftKey)){e.preventDefault();historyStep(false);}
  });
}
historyButtons();

// Display unit system.
// Geometry and 3MF always use millimeters internally.
const UNIT_FACTORS={mm:1,cm:10,in:25.4};
const UNIT_DECIMALS={mm:2,cm:2,in:2};

/** Display a number the way the mockup wants it: millimetres and centimetres
 *  drop trailing zeros (so 250, 0.4, 2 - not 250.00), while inches always keep
 *  two decimals because whole inches are rare and the extra precision matters
 *  once a millimetre model is measured in them. */
function fmtNum(v){
  if(!Number.isFinite(v)) v=0;
  if(currentUnit()==='in') return v.toFixed(2);
  let s=v.toFixed(2);
  if(s.indexOf('.')>=0) s=s.replace(/0+$/,'').replace(/\.$/,'');
  return s;
}

// Smallest sensible increment per unit. Deriving this from the mm step
// used to give 0.01/25.4 = 0.0004 in, which rounded to 0.000 at three
// decimals and left the +/- buttons doing nothing in inches.
const UNIT_STEPS={mm:0.01,cm:0.001,in:0.001};

// Coarse step for the sliders and the +/- buttons. The fine step is right
// for typing an exact figure but useless to click: at 0.01 mm a button press
// moved the width by a hundredth of a millimetre, and dragging the slider
// produced values like 249.96 rather than a round number.
const UNIT_COARSE={mm:1,cm:0.1,in:0.05};

function unitCoarse(unit=currentUnit()){
  return UNIT_COARSE[unit];
}

// Every dimension is stored canonically in millimetres. The inputs only
// ever show a rounded representation of it, so re-reading them on a unit
// switch would lose precision on each conversion.
const dimsMM={};

function setMM(id,mm){dimsMM[id]=mm}

function getMM(id){
  return dimsMM[id]!==undefined ? dimsMM[id] : toMM($(id).value);
}

function unitStep(unit=currentUnit()){
  return UNIT_STEPS[unit];
}

function currentUnit(){
  return $('unitSelect').value;
}

/**
 * Parses a typed number.
 *
 * A Czech keyboard puts a comma on the numeric block, and <input type=number>
 * refuses it outright: the field read back as empty, the value became 0 and
 * the slider appeared not to react at all. Spaces are stripped too, since
 * people paste "1 000".
 */
function parseNum(raw){
  const s=String(raw).replace(/[\s\u00A0]/g,'').replace(',','.');
  const v=parseFloat(s);
  return Number.isFinite(v)?v:NaN;
}

function toMM(value){
  const v=parseNum(value);
  return (Number.isFinite(v)?v:0)*UNIT_FACTORS[currentUnit()];
}

function fromMM(value){
  return value/UNIT_FACTORS[currentUnit()];
}

function formatUnit(valueMM){
  return fmtNum(fromMM(valueMM));
}

/**
 * The ceiling of a parameter in millimetres, from the shop's settings.
 *
 * Same numbers the fields are rendered with and the export is validated
 * against; the fallbacks are only for a page served without them.
 */
const RANGES=(window.H3D_LIMITS&&window.H3D_LIMITS.ranges)||{};

function unitMaxMM(id){
  const key=(id==='maxPrintW'||id==='maxPrintD')?'print':id;
  const r=RANGES[key];
  if(r&&isFinite(+r[1])) return +r[1];
  return (id==='dw'||id==='dd') ? 1000 : 500;
}

function unitMaxDisplay(id){
  return fromMM(unitMaxMM(id));
}

/**
 * Sentences that name the unit rather than just showing it.
 *
 * The footer used to say "in millimetres" for ever, including with inches
 * selected, because it was a fixed key. It is filled from the same place
 * the unit lives, so switching to inches rewrites it.
 */
function refreshUnitCopy(){
  const el=$('footUnits');
  if(el) el.textContent=t('foot.units',t('unit.'+currentUnit()+'.long'));
}

function refreshUnitUI(){
  const unit=currentUnit();
  document.querySelectorAll('.unit-label').forEach(el=>el.textContent=unit);
  refreshUnitCopy();

  ['dw','dd','dh'].forEach(id=>{
    const input=$(id), slider=$(id+'Slider');
    const maxMM=unitMaxMM(id);
    const maxDisplay=unitMaxDisplay(id);

    input.min=unitStep(unit);
    input.max=maxDisplay;
    input.step=unitStep(unit);

    slider.min=0;
    slider.max=maxDisplay;
    slider.step=unitCoarse(unit);

    const clampedMM=clampValue(getMM(id),0,maxMM);
    setMM(id,clampedMM);
    input.value=formatUnit(clampedMM);
    slider.value=fromMM(clampedMM);
    updateRange(id+'Slider');

    updateDimensionLabel(id,clampedMM);
    const minEl=$(id+'Min'),maxEl=$(id+'Max');
    if(minEl)minEl.textContent='0 '+unit;
    if(maxEl)maxEl.textContent=formatUnit(maxMM)+' '+unit;
  });

  // Construction dimensions use the same display unit.
  ['gap','wall','bottom','radius','outer'].forEach(id=>{
    const input=$(id);
    if(!input)return;
    const valueMM=Math.max(0,getMM(id));
    setMM(id,valueMM);
    input.value=formatUnit(valueMM);
    input.step=unitStep(unit);
    input.min=0;
    // Keep the slider and its 0…max labels in the freshly-selected unit.
    const slider=$(id+'Slider');
    if(slider){
      const mn=$(id+'Min'), mx=$(id+'Max');
      if(mn) mn.textContent=formatUnit(parseFloat(slider.min))+' '+unit;
      if(mx) mx.textContent=formatUnit(parseFloat(slider.max))+' '+unit;
      const lo=parseFloat(slider.min)||0, hi=parseFloat(slider.max)||100;
      slider.value=Math.max(lo,Math.min(hi,valueMM));
      updateRange(id+'Slider');
      if(id==='radius') updateRadiusPreview();
    }
  });

  // The height's low end is the floor plus a millimetre, not zero, and the
  // loop above has just written a plain "0" under the slider in the new unit.
  enforceMinHeight();

  // Print-area limits track the display unit too. 500 mm bed → 50 cm → 19.69 in.
  ['maxPrintW','maxPrintD'].forEach(id=>{
    const input=$(id), slider=$(id+'Slider');
    if(!input)return;
    // From the settings, not a 500 written here: this runs at startup and
    // on every unit change, so a hardcoded number quietly overwrote the
    // limit the page had just been rendered with.
    const maxMM=unitMaxMM(id), maxDisplay=fromMM(maxMM);
    const clampedMM=clampValue(getMM(id),0,maxMM);
    setMM(id,clampedMM);
    input.step=unitStep(unit); input.min=0; input.max=maxDisplay;
    input.value=fmtNum(fromMM(clampedMM));
    if(slider){
      slider.min=0; slider.max=maxDisplay; slider.step=unitCoarse(unit);
      slider.value=fromMM(clampedMM);
      updateRange(id+'Slider');
    }
    const mx=$(id+'Max');
    if(mx) mx.textContent=fmtNum(maxDisplay)+' '+unit;
  });

  draw();
}

// High precision dimension controls.
// Manual typing is kept independent from the slider so the cursor never jumps.
// Sliders use 0.01 mm resolution and update the preview smoothly.
/**
 * Repaints the coloured part of a range track.
 *
 * The fill is drawn from a CSS variable, and the browser only recalculates
 * it when the slider fires its own input event. Setting slider.value from
 * code moves the thumb but leaves the fill where it was, which is why typing
 * a width made the slider look stuck: the handle had moved, the blue bar had
 * not. Anything that sets a slider value has to call this afterwards.
 */
function updateRange(sliderId){
  const el=$(sliderId);
  if(!el)return;
  const min=parseFloat(el.min)||0;
  const max=parseFloat(el.max)||100;
  const value=parseFloat(el.value)||0;
  const pct=max>min?((value-min)/(max-min))*100:0;
  el.style.setProperty('--range-progress',Math.max(0,Math.min(100,pct))+'%');
}

function clampValue(v,min,max){
  return Math.min(max,Math.max(min,v));
}

let dimensionFrame=null;

function updateDimensionLabel(id,valueMM){
  const label=$(id+'Value');
  if(label)label.textContent=formatUnit(valueMM)+' '+currentUnit();
}

/**
 * The floor decides how low the height is allowed to go.
 *
 * A box has to stand a millimetre taller than its own floor or the server
 * refuses the export, so rather than let the drawer drift into that state and
 * then complain about it, the floor carries the height's limit with it: the
 * number field, the slider and the label at the low end of the slider all
 * move to floor + 1 mm. A height that is already below the new limit is
 * lifted onto it; one with room to spare is left exactly where it was put.
 *
 * @param {boolean} lift raise a height that already sits below the limit
 * @return {boolean} whether the height had to be moved
 */
function enforceMinHeight(lift=true){
  const minMM=getMM('bottom')+1;
  const input=$('dh'), slider=$('dhSlider'), endLabel=$('dhMin');
  const shown=fromMM(minMM);
  // A floor so thick that the limit passes the top of the slider would leave
  // the slider with min above max, which browsers resolve in their own ways.
  const roomOnSlider=!slider||shown<(parseFloat(slider.max)||Infinity);
  /*
   * The limit is only written onto the controls once the height actually
   * clears it. A loaded project may arrive too short, and a slider whose min
   * sits above its value gets silently clamped by the browser - the handle
   * would then show a height the drawer does not have. In that case the
   * label still names the limit, the Inspector says what is wrong and Fix
   * puts it right; the controls keep telling the truth in the meantime.
   */
  const willClear=lift||getMM('dh')>=minMM-1e-9;
  if(input) input.min=willClear?fmtNum(shown):'0';
  if(slider&&roomOnSlider) slider.min=willClear?fmtNum(shown):'0';
  if(endLabel) endLabel.textContent=formatUnit(minMM)+' '+currentUnit();

  if(!lift||getMM('dh')>=minMM-1e-9){ if(slider) updateRange('dhSlider'); return false; }
  setMM('dh',minMM);
  if(input) input.value=formatUnit(minMM);
  if(slider){ slider.value=shown; updateRange('dhSlider'); }
  updateDimensionLabel('dh',minMM);
  return true;
}

function redrawDimensionPreview(){
  if(dimensionFrame!==null)return;
  dimensionFrame=requestAnimationFrame(()=>{
    dimensionFrame=null;
    draw();
  });
}

function setDimensionFromSlider(id,value){
  const input=$(id);
  const slider=$(id+'Slider');
  const v=clampValue(parseNum(value)||0,0,unitMaxDisplay(id));
  const mm=v*UNIT_FACTORS[currentUnit()];

  setMM(id,mm);
  input.value=fmtNum(v);
  slider.value=v;
  updateRange(id+'Slider');
  updateDimensionLabel(id,mm);
  redrawDimensionPreview();
}

function setDimensionFromInput(id,commit=false){
  const input=$(id);
  const slider=$(id+'Slider');
  let raw=input.value.trim();

  if(raw===''){
    setMM(id,0);
    updateDimensionLabel(id,0);
    return;
  }

  let v=parseNum(raw);
  if(!Number.isFinite(v))return;

  v=clampValue(v,0,unitMaxDisplay(id));
  setMM(id,v*UNIT_FACTORS[currentUnit()]);
  slider.value=v;
  updateRange(id+'Slider');
  updateDimensionLabel(id,v*UNIT_FACTORS[currentUnit()]);

  if(commit)input.value=fmtNum(v);
  redrawDimensionPreview();
}

/** Applies a stepped value to the field, its slider and the canvas. */
function commitStep(id,input,v){
  v=clampValue(v,0,unitMaxDisplay(id));
  input.value=fmtNum(v);
  setMM(id,v*UNIT_FACTORS[currentUnit()]);
  $(id+'Slider').value=v;
  updateRange(id+'Slider');
  updateDimensionLabel(id,v*UNIT_FACTORS[currentUnit()]);
  draw();
}

function stepDimension(id,dir,fine=false){
  const input=$(id);
  let v=parseNum(input.value);
  if(!Number.isFinite(v))v=0;

  // Shift gives the fine step, for the rare case where a hundredth matters.
  const step=fine?unitStep():unitCoarse();

  if(fine){
    v=v+dir*step;
  }else{
    // Move to the next round value in the direction pressed. Rounding to
    // the nearest first and then stepping skipped one: from 249.96 the plus
    // button jumped to 251 instead of landing on 250.
    const onStep=Math.abs(v/step-Math.round(v/step))<1e-9;
    v=onStep
      ? v+dir*step
      : (dir>0 ? Math.ceil(v/step)*step : Math.floor(v/step)*step);
  }

  // Floating point leaves 0.30000000000000004 behind, which then shows up in
  // the field, so the value is rounded back to the unit's precision.
  v=parseFloat(fmtNum(v));
  v=clampValue(v,0,unitMaxDisplay(id));

  setMM(id,v*UNIT_FACTORS[currentUnit()]);
  input.value=fmtNum(v);
  $(id+'Slider').value=v;
  updateRange(id+'Slider');
  updateDimensionLabel(id,v*UNIT_FACTORS[currentUnit()]);
  redrawDimensionPreview();
}

['dw','dd','dh'].forEach(id=>{
  $(id).addEventListener('input',()=>setDimensionFromInput(id,false));
  // Enter finishes the field. Without it the caret stays in the input and
  // Delete edits text instead of removing the selected box, which reads as
  // the key not working at all.
  $(id).addEventListener('keydown',e=>{
    if(e.key==='Enter'){e.preventDefault();setDimensionFromInput(id,true);e.target.blur()}
  });
  $(id).addEventListener('change',()=>setDimensionFromInput(id,true));
  $(id).addEventListener('blur',()=>setDimensionFromInput(id,true));
  $(id+'Slider').addEventListener('input',e=>setDimensionFromSlider(id,e.target.value));
});

document.querySelectorAll('[data-step]').forEach(btn=>{
  btn.title=t('step.hint');
  btn.addEventListener('click',e=>stepDimension(btn.dataset.step,+btn.dataset.dir,e.shiftKey));
});

/** Live rounded-corner preview beside the radius field. */
function updateRadiusPreview(){
  const rect=document.getElementById('radiusRect'), sl=$('radiusSlider');
  if(!rect||!sl)return;
  const max=parseFloat(sl.max)||20;
  const rx=Math.max(0,Math.min(9,(getMM('radius')/max)*9));
  rect.setAttribute('rx',rx.toFixed(1));
}

/** Wall, floor and radius now carry a slider (in millimetres) alongside the
 *  number. Gap keeps just its field. Slider and field stay in step. */
['gap','wall','bottom','radius','outer'].forEach(id=>{
  const input=$(id); if(!input)return;
  const slider=$(id+'Slider');
  const clampMM=mm=>{
    if(!slider) return mm;
    const mn=parseFloat(slider.min)||0, mx=parseFloat(slider.max)||100;
    return Math.max(mn,Math.min(mx,mm));
  };
  const syncSlider=()=>{
    if(!slider)return;
    slider.value=clampMM(getMM(id));
    updateRange(id+'Slider');
    if(id==='radius') updateRadiusPreview();
  };
  // Rounding the corners by hand is the stronger statement: wall mode steps
  // aside and does NOT hand back the radius it parked, because you have just
  // chosen a new one.
  const dropWallMode=()=>{};
  input.addEventListener('input',()=>{ setMM(id,toMM(input.value)); syncSlider(); dropWallMode(); if(id==='bottom') enforceMinHeight(); draw(); });
  input.addEventListener('keydown',e=>{ if(e.key==='Enter'){e.preventDefault();e.target.blur()} });
  input.addEventListener('blur',()=>{
    const v=parseNum(input.value);
    if(Number.isFinite(v)) input.value=formatUnit(getMM(id));
  });
  if(slider){
    slider.addEventListener('input',()=>{
      const mm=parseFloat(slider.value)||0;
      setMM(id,mm);
      input.value=formatUnit(mm);
      updateRange(id+'Slider');
      if(id==='radius') updateRadiusPreview();
      // Dragging the floor up pushes the height's floor ahead of it, so the
      // slider cannot be used to build a box shorter than its own bottom.
      if(id==='bottom') enforceMinHeight();
      dropWallMode();
      draw();
    });
    const mn=$(id+'Min'), mx=$(id+'Max');
    if(mn) mn.textContent=formatUnit(parseFloat(slider.min))+' '+currentUnit();
    if(mx) mx.textContent=formatUnit(parseFloat(slider.max))+' '+currentUnit();
    syncSlider();
  }
});

// Max print area: shown in the current display unit but stored canonically in
// millimetres, exactly like the drawer dimensions. Number field and slider stay
// in step; re-check on every edit.
// The printer bed a user may enter, from the shop's settings.
const PRINT_MAX_MM=unitMaxMM('maxPrintW');
['maxPrintW','maxPrintD'].forEach(id=>{
  const el=$(id), sl=$(id+'Slider');
  if(!el)return;
  // Seed the canonical value from whatever the HTML starts with (0 = no limit).
  setMM(id,parseNum(el.value)||0);
  el.addEventListener('input',()=>{
    const disp=clampValue(parseNum(el.value)||0,0,fromMM(PRINT_MAX_MM));
    setMM(id,disp*UNIT_FACTORS[currentUnit()]);
    if(sl){ sl.value=disp; updateRange(id+'Slider'); }
    draw();
  });
  if(sl){
    sl.addEventListener('input',()=>{
      const disp=parseFloat(sl.value)||0;
      setMM(id,disp*UNIT_FACTORS[currentUnit()]);
      el.value=fmtNum(disp);
      updateRange(id+'Slider');
      draw();
    });
    updateRange(id+'Slider');
  }
});

// Columns and rows are sliders now. Growing the grid just adds room; shrinking
// drops the boxes that no longer fit. draw() re-lays everything at the new cell
// size, so the current arrangement is kept.
/**
 * Everything that hangs off the number of columns and rows, brought back in
 * step: the box size pickers, the two read-outs beside the sliders, the
 * slider fills, and the generator's own min/max choosers.
 *
 * One function because there is more than one way the grid changes. Moving
 * the slider went through onGridChange and updated all of it; the inspector's
 * Fix set the values straight and updated almost none of it, which left the
 * generator offering last time's columns and the sliders showing a grid that
 * was no longer on the canvas.
 */
/**
 * Write a number into a read-out that may be a plain element or an input.
 *
 * The column and row counters became editable boxes, like every other
 * parameter; setting .textContent on an <input> writes nothing visible.
 */
function setReadout(id,value){
  const el=$(id);
  if(!el)return;
  if('value' in el && el.tagName==='INPUT') el.value=value; else el.textContent=value;
}

function syncGridUI(){
  const g=grid();
  if($('bw')){ options($('bw'),g.c); options($('bd'),g.r); }
  setReadout('colsVal',g.c);
  setReadout('rowsVal',g.r);
  updateRange('cols'); updateRange('rows');
  syncRandomOptions();
}

function onGridChange(){
  const g=grid();
  boxes=boxes.filter(b=>b.x>=0&&b.y>=0&&b.x+b.w<=g.c&&b.y+b.h<=g.r);
  if(selected!==null && !boxes.some(b=>b.id===selected)) selected=null;
  syncGridUI();
  draw();
}
[$('cols'),$('rows')].forEach(x=>{
  if(!x)return;
  x.addEventListener('input',onGridChange);
  x.addEventListener('change',onGridChange);
});

// The count boxes are inputs now, so they can be typed into as well as
// dragged. Anything outside 1..limit is pulled back on commit rather than
// refused while you are still typing.
[['colsVal','cols'],['rowsVal','rows']].forEach(([boxId,sliderId])=>{
  const box=$(boxId), slider=$(sliderId);
  if(!box||!slider)return;
  const commit=()=>{
    const n=Math.max(1,Math.min(MAXCELLS,parseInt(box.value,10)||1));
    box.value=n;
    if(+slider.value!==n){ slider.value=n; onGridChange(); }
  };
  box.addEventListener('change',commit);
  box.addEventListener('blur',commit);
  box.addEventListener('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); commit(); } });
});

updateRange('cols'); updateRange('rows');

/** Keep the random min/max width & depth choosers in step with the grid, so
 *  the depth can go up to the real number of rows and width up to the columns.
 *  Current picks are kept where they still fit. */
function syncRandomOptions(){
  if(!$('randomMinW'))return;
  const g=grid();
  // The max choosers follow the grid's maximum, so setting a bigger grid lets
  // the generator immediately make bigger boxes; the min ones keep their pick.
  [['randomMinW',g.c,false],['randomMaxW',g.c,true],['randomMinD',g.r,false],['randomMaxD',g.r,true]].forEach(([id,max,isMax])=>{
    const prev=+$(id).value||1;
    options($(id),max);
    $(id).value = isMax ? max : Math.min(Math.max(1,prev),max);
  });
}

// Initialize the unit-aware controls.
['dw','dd','dh'].forEach(id=>{
  const v=clampValue(parseNum($(id).value)||0,0,unitMaxDisplay(id));
  setMM(id,v*UNIT_FACTORS[currentUnit()]);
  $(id).value=fmtNum(v);
  $(id+'Slider').value=v;
  updateRange(id+'Slider');
  updateDimensionLabel(id,v*UNIT_FACTORS[currentUnit()]);
});

['gap','wall','bottom','radius','outer'].forEach(id=>{
  setMM(id,toMM($(id).value));
});

// The height's limit comes from the floor, so it can only be set once the
// floor has a value in millimetres - which is the line above.
enforceMinHeight();

// The first paint happened before those four had a value in millimetres, so
// anything derived from them (the "boxes take" figure on the drawer chip)
// came out blank until the first edit. One more pass, now that they are set.
draw();

// Remember the chosen unit for next time (per browser). The server default
// stays millimetres; this only affects what the studio shows on return.
function persistUnit(u){
  try{ localStorage.setItem('honza3d-unit',u); }catch(_){}
  try{ document.cookie='h3d_unit='+u+';path=/;max-age=31536000;samesite=lax'; }catch(_){}
}
$('unitSelect').addEventListener('change',()=>{ persistUnit(currentUnit()); refreshUnitUI(); });
// Restore a previously chosen unit before the first paint uses it. Signed-in
// users get theirs from the profile (applyStudioPrefs) instead, so the local
// leftovers of some guest session must not flash in first.
(function initUnit(){
  if(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn)return;
  let saved=null; try{ saved=localStorage.getItem('honza3d-unit'); }catch(_){}
  if(saved && UNIT_FACTORS[saved] && saved!==currentUnit()){
    $('unitSelect').value=saved;
    refreshUnitUI();
  }
})();
document.querySelectorAll('[data-layout]').forEach(b=>b.onclick=()=>b.dataset.layout==='mixed'?runGenerate(b,mixed):($('cols').value=4,$('rows').value=4,boxes=[],selected=null,draw()));
if($('randomBtn')) $('randomBtn').addEventListener('click',()=>runGenerate($('randomBtn'),randomLayout));

/* ---- Folding the generator away ---- */
// On a phone the settings fill the screen, so the drawing they describe is
// nowhere to be seen. Folded, only the buttons remain - which is all you
// need once the numbers are set. The choice is remembered.
(function foldLayoutPanel(){
  const panel=$('layoutPanel'), btn=$('layoutFold');
  if(!panel||!btn)return;

  let folded=false;
  try{ folded=localStorage.getItem('honza3d-layout-folded')==='1'; }catch(_){}

  const paint=()=>{
    panel.classList.toggle('is-folded',folded);
    btn.setAttribute('aria-expanded',folded?'false':'true');
    btn.title=folded?'Rozbalit nastavení':'Sbalit nastavení';
  };
  paint();

  btn.addEventListener('click',()=>{
    folded=!folded;
    paint();
    try{ localStorage.setItem('honza3d-layout-folded',folded?'1':'0'); }catch(_){}
  });
})();

/** Run a synchronous, possibly slow layout build behind a spinner. The
 *  generator locks the main thread while it packs the grid, so on a big grid
 *  the button looked frozen. Paint the busy state across two frames first,
 *  then run, so the spinner is actually on screen before the thread stalls. */
function runGenerate(btn,fn){
  if(btn && btn.classList.contains('is-busy'))return;
  if(btn){ btn.classList.add('is-busy'); btn.disabled=true; }
  const done=()=>{ if(btn){ btn.classList.remove('is-busy'); btn.disabled=false; } };
  requestAnimationFrame(()=>requestAnimationFrame(()=>{ try{ fn(); } finally{ done(); } }));
}

/* ---------------------------------------------------------------
   Export
   The mesh itself is generated on the server. The browser only ships
   the layout description and receives a finished file back.
   --------------------------------------------------------------- */

function collectLayout(){
 const g=grid();
 return {
   token: window.H3D_TOKEN,
   dw:getMM('dw'),
   dd:getMM('dd'),
   dh:getMM('dh'),
   gap:getMM('gap'),
   wall:getMM('wall'),
   bottom:getMM('bottom'),
   radius:getMM('radius'),
   // Clamped the same way the drawing clamps it, so what gets built is what
   // was on screen even if somebody typed an inset larger than the drawer.
   outer:outerMM(Math.max(1,getMM('dw')),Math.max(1,getMM('dd'))),
   cols:g.c,
   rows:g.r,
   boxes:boxes.map(b=>({
     x:b.x,y:b.y,w:b.w,h:b.h,
     ...(isPoly(b)?{cells:cellsOf(b).map(c=>({x:c.x,y:c.y}))}:{}),
     // Only what is on screen gets built: with the corners rounded the
     // dividers are not drawn, so they are not sent either.
     ...(dividersOn()&&hasWalls(b)?{walls:b.walls.slice()}:{}),
     ...(dividersOn()&&hasMidWalls(b)?{midWalls:b.midWalls.slice()}:{}),
     ...(dividersOn()&&hasHalfWalls(b)?{halfWalls:b.halfWalls.slice()}:{} )
   }))
 };
}

function setExportBusy(busy){
 document.querySelectorAll('[data-export]').forEach(el=>{
   el.classList.toggle('is-busy',busy);
   el.disabled=busy;
 });
 const trigger=$('exportTrigger');
 if(trigger){
   trigger.classList.toggle('is-busy',busy);
   trigger.disabled=busy;
 }
}

function showExportError(message){
 const bar=$('exportError');
 if(!bar){alert(message);return}
 bar.textContent=message;
 bar.classList.remove('note');
 bar.classList.add('visible');
 clearTimeout(showExportError._t);
 showExportError._t=setTimeout(()=>bar.classList.remove('visible'),6000);
}

/**
 * Modal confirmation. Returns a promise that resolves true when the user
 * agrees. Built rather than using window.confirm so it can be styled, can
 * carry the balance, and cannot be suppressed by the browser's "block
 * further dialogs" checkbox after a couple of exports.
 */
function confirmSpend(cost,balance){
  return new Promise(resolve=>{
    const back=document.createElement('div');
    back.className='modal-back';

    const box=document.createElement('div');
    box.className='modal';
    box.setAttribute('role','dialog');
    box.setAttribute('aria-modal','true');

    const h=document.createElement('h3');
    h.textContent=t('spend.title');

    const p=document.createElement('p');
    p.textContent=t('spend.body',cost,balance,Math.max(0,balance-cost));

    const row=document.createElement('div');
    row.className='modal-actions';

    const cancel=document.createElement('button');
    cancel.type='button';cancel.className='btn';cancel.textContent=t('spend.cancel');

    const okBtn=document.createElement('button');
    okBtn.type='button';okBtn.className='btn primary';okBtn.textContent=t('spend.ok');

    row.appendChild(cancel);row.appendChild(okBtn);
    box.appendChild(h);box.appendChild(p);box.appendChild(row);
    back.appendChild(box);
    document.body.appendChild(back);

    const done=v=>{
      document.removeEventListener('keydown',key);
      back.remove();
      resolve(v);
    };
    const key=e=>{
      if(e.key==='Escape')done(false);
      if(e.key==='Enter')done(true);
    };

    cancel.addEventListener('click',()=>done(false));
    okBtn.addEventListener('click',()=>done(true));
    back.addEventListener('click',e=>{if(e.target===back)done(false)});
    document.addEventListener('keydown',key);

    okBtn.focus();
  });
}

/**
 * Generic yes/no confirmation, styled the same as confirmSpend so a
 * destructive action like clearing the layout never rides on the browser's
 * own dialog - which can be silenced after a couple of uses.
 */
/**
 * A yes/no dialog. With a rememberKey it also offers "do not show again",
 * and answers true straight away next time - a reminder worth reading once
 * is not worth reading on every single export.
 */
function confirmAction(title,body,okLabel,rememberKey){
  if(rememberKey){
    try{ if(localStorage.getItem(rememberKey)==='1') return Promise.resolve(true); }catch(_){}
  }
  return new Promise(resolve=>{
    const back=document.createElement('div');
    back.className='modal-back';

    const box=document.createElement('div');
    box.className='modal';
    box.setAttribute('role','dialog');
    box.setAttribute('aria-modal','true');

    const h=document.createElement('h3');
    h.textContent=title;

    const p=document.createElement('p');
    p.textContent=body;

    const row=document.createElement('div');
    row.className='modal-actions';

    const cancel=document.createElement('button');
    cancel.type='button';cancel.className='btn';cancel.textContent=t('spend.cancel');

    const okBtn=document.createElement('button');
    okBtn.type='button';okBtn.className='btn primary';okBtn.textContent=okLabel;

    row.appendChild(cancel);row.appendChild(okBtn);
    box.appendChild(h);box.appendChild(p);

    let remember=null;
    if(rememberKey){
      const label=document.createElement('label');
      label.className='modal-check';
      remember=document.createElement('input');
      remember.type='checkbox';
      const span=document.createElement('span');
      span.textContent=t('confirm.dontask');
      label.appendChild(remember);label.appendChild(span);
      box.appendChild(label);
    }

    box.appendChild(row);
    back.appendChild(box);
    document.body.appendChild(back);

    const done=v=>{
      document.removeEventListener('keydown',key);
      if(v&&rememberKey&&remember&&remember.checked){
        try{ localStorage.setItem(rememberKey,'1'); }catch(_){}
      }
      back.remove();
      resolve(v);
    };
    const key=e=>{
      if(e.key==='Escape')done(false);
      if(e.key==='Enter')done(true);
    };

    cancel.addEventListener('click',()=>done(false));
    okBtn.addEventListener('click',()=>done(true));
    back.addEventListener('click',e=>{if(e.target===back)done(false)});
    document.addEventListener('keydown',key);

    okBtn.focus();
  });
}

async function runExport(format){
 if(hasTooMany){
   showExportError(t('err.toomany'));
   return;
 }
 if(hasOversize){
   showExportError(t('err.oversize'));
   return;
 }
 if(!$('status').classList.contains('ok')){
   showExportError(t('err.fixlayout'));
   return;
 }
 if(!boxes.length){
   showExportError(t('err.nobox'));
   return;
 }
 // Said here rather than let the server say it after the credit is spent.
 if(dividersOn()&&anyWallsInvalid()){
   showExportError(t('err.wallfloats'));
   return;
 }

 /*
  * One look before the plastic. The studio can only be as right as the
  * numbers it was given - a drawer measured across the front instead of
  * inside it comes out wrong here and nowhere else - so the last step is a
  * reminder to open the file and check it. Once is enough: it can be turned
  * off, and it remembers.
  */
 closeExportMenu();
 if(!await confirmAction(t('pre.title'),t('pre.body'),t('pre.ok'),'honza3d-preflight')){
   return;
 }

 // The free allowance is gone and this export will cost credits. Asking
 // first means a spent credit is never a surprise.
 if(QUOTA.mode==='credit'&&QUOTA.confirmSpend){
   closeExportMenu();
   const go=await confirmSpend(QUOTA.cost,QUOTA.balance);
   if(!go)return;
 }

 closeExportMenu();
 setExportBusy(true);

 try{
   const res=await fetch('api/export.php',{
     method:'POST',
     headers:{'Content-Type':'application/json'},
     body:JSON.stringify({...collectLayout(),format})
   });

   if(!res.ok){
     let msg=t('err.http',res.status);
     let payload=null;
     try{ payload=await res.json(); }catch(_){}
     if(payload&&payload.error) msg=payload.error;

     // 401 and 429 are quota answers, not faults: re-sync so the banner
     // and the countdown reflect what the server just decided.
     if(res.status===401||res.status===429){
       refreshQuota();
     }
     throw Error(msg);
   }

   // Credits can be fractional, so these are parsed as decimals: parseInt
   // turned a charge of 0.5 into 0 and swallowed the notice entirely.
   const chargedRaw=res.headers.get('X-H3D-Charged')||'0';
   const leftRaw=res.headers.get('X-H3D-Credits')||'';
   const charged=parseFloat(chargedRaw);
   if(leftRaw!==''&&QUOTA.signedIn) setBalance(leftRaw);
   if(charged>0) showExportNote(t('quota.charged',chargedRaw,leftRaw));

   // Filename comes from the server so the timestamp matches the model.
   const disp=res.headers.get('Content-Disposition')||'';
   const match=/filename="([^"]+)"/.exec(disp);
   const name=match?match[1]:'Honza3D_Drawer_Organizer.'+format;

   const blob=await res.blob();
   const url=URL.createObjectURL(blob);
   const a=document.createElement('a');
   a.href=url;a.download=name;
   document.body.appendChild(a);a.click();a.remove();
   setTimeout(()=>URL.revokeObjectURL(url),1000);
 }catch(e){
   showExportError(e.message||t('err.failed'));
 }finally{
   setExportBusy(false);
   // The cooldown has just restarted, so the banner needs the new window.
   refreshQuota();
 }
}

/** Neutral counterpart to showExportError, used for credit notices. */
function showExportNote(message){
 const bar=$('exportError');
 if(!bar)return;
 bar.textContent=message;
 bar.classList.add('visible','note');
 clearTimeout(showExportNote._t);
 showExportNote._t=setTimeout(()=>bar.classList.remove('visible','note'),5000);
}

/* ---- Export split button with hover / click menu ---- */

let exportMenuTimer=null;

function openExportMenu(){
 clearTimeout(exportMenuTimer);
 const menu=$('exportMenu');
 if(!menu)return;
 menu.classList.add('open');
 $('exportTrigger').setAttribute('aria-expanded','true');
}

function closeExportMenu(){
 const menu=$('exportMenu');
 if(!menu)return;
 menu.classList.remove('open');
 const trigger=$('exportTrigger');
 if(trigger)trigger.setAttribute('aria-expanded','false');
}

function initExportMenu(){
 // The format buttons live in the Export panel and must work even without
 // the old split-button dropdown - the flat panels dropped exportSplit and
 // its early return here silently left every button dead.
 document.querySelectorAll('[data-export]').forEach(btn=>{
   btn.addEventListener('click',e=>{
     e.stopPropagation();
     runExport(btn.dataset.export);
   });
 });

 const wrap=$('exportSplit');
 const trigger=$('exportTrigger');
 if(!wrap||!trigger)return;

 // Hover opens it on the desktop; the close is delayed so the pointer can
 // travel across the gap between the button and the menu.
 wrap.addEventListener('mouseenter',openExportMenu);
 wrap.addEventListener('mouseleave',()=>{
   clearTimeout(exportMenuTimer);
   exportMenuTimer=setTimeout(closeExportMenu,180);
 });

 // Touch and keyboard need an explicit toggle.
 trigger.addEventListener('click',e=>{
   e.stopPropagation();
   $('exportMenu').classList.contains('open')?closeExportMenu():openExportMenu();
 });

 trigger.addEventListener('keydown',e=>{
   if(e.key==='ArrowDown'||e.key==='Enter'||e.key===' '){
     e.preventDefault();
     openExportMenu();
     const first=$('exportMenu').querySelector('[data-export]');
     if(first)first.focus();
   }
 });

 document.addEventListener('click',closeExportMenu);
 document.addEventListener('keydown',e=>{
   if(e.key==='Escape'){closeExportMenu();trigger.focus()}
 });
}

initExportMenu();

/* ---- Collapsible sidebar sections ---- */
/* workspace-persistence-v23: account-scoped cache + strictly monotonic save timestamps */

function initCollapsibles(){
 document.querySelectorAll('.panel-head[data-toggle]').forEach(head=>{
   head.addEventListener('click',()=>{
     const panel=head.closest('.panel');
     panel.classList.toggle('collapsed');
     head.setAttribute('aria-expanded',panel.classList.contains('collapsed')?'false':'true');
   });
   head.addEventListener('keydown',e=>{
     if(e.key==='Enter'||e.key===' '){e.preventDefault();head.click()}
   });
 });
}

initCollapsibles();

refreshUnitUI();
initRandomOptions();
// Workspace cache is account-scoped. Keep this helper in the same outer
// script scope as savePrefsDebounced()/sendWorkspaceNow(); putting it inside
// the signed-in boot branch makes F5 saves throw ReferenceError after boot.
const workspaceStorageKey=()=>{
  const uid=Number(window.H3D_QUOTA&&window.H3D_QUOTA.userId)||0;
  return uid>0?'h3d_workspace_v2_u'+uid:'h3d_workspace_v2';
};

(function(){
  const $id=id=>document.getElementById(id);

  ['dwSlider','ddSlider','dhSlider'].forEach(id=>{
    const el=$id(id);
    if(!el)return;
    updateRange(id);
    el.addEventListener('input',()=>updateRange(id));
    el.addEventListener('change',()=>updateRange(id));
  });})();

/* ---------------------------------------------------------------
   The "i" beside a setting

   A single floating bubble, moved to whichever dot is being pointed at.
   Positioned with position:fixed rather than inside the panel, because the
   settings live in popovers that scroll and clip their own contents - a
   CSS-only tooltip in there gets cut in half.
   --------------------------------------------------------------- */
(function(){
  let tip=null;

  function bubble(){
    if(!tip){
      tip=document.createElement('div');
      tip.className='info-tip';
      tip.hidden=true;
      tip.setAttribute('role','tooltip');
      document.body.appendChild(tip);
    }
    return tip;
  }

  function show(dot){
    const key=dot.dataset.i18nTip;
    if(!key)return;
    const el=bubble();
    el.textContent=t(key);
    el.hidden=false;

    const d=dot.getBoundingClientRect();
    const b=el.getBoundingClientRect();
    const pad=8;
    // Beside the dot where there is room, above or below it when there is
    // not, and never off the edge of the window.
    let left=d.right+10;
    if(left+b.width>window.innerWidth-pad) left=Math.max(pad,d.left-b.width-10);
    let top=d.top+d.height/2-b.height/2;
    top=Math.max(pad,Math.min(top,window.innerHeight-b.height-pad));
    el.style.left=Math.round(left)+'px';
    el.style.top=Math.round(top)+'px';
  }

  function hide(){ if(tip) tip.hidden=true; }

  // Both event families: a mouse fires pointerover, but a few stacks (and
  // every automated browser) only give the mouse events. show() is
  // idempotent, so hearing it twice costs nothing.
  ['pointerover','mouseover'].forEach(ev=>{
    document.addEventListener(ev,e=>{
      const dot=e.target.closest?e.target.closest('.info-dot'):null;
      if(dot) show(dot);
    });
  });
  ['pointerout','mouseout'].forEach(ev=>{
    document.addEventListener(ev,e=>{
      if(e.target.closest&&e.target.closest('.info-dot')) hide();
    });
  });
  document.addEventListener('focusin',e=>{
    const dot=e.target.closest?e.target.closest('.info-dot'):null;
    if(dot) show(dot); else hide();
  });
  document.addEventListener('focusout',hide);
  // Touch: a tap opens it, the next tap anywhere closes it. The dot is a
  // button, so it also has to not submit or scroll anything.
  document.addEventListener('click',e=>{
    const dot=e.target.closest?e.target.closest('.info-dot'):null;
    if(dot){ e.preventDefault(); show(dot); return; }
    hide();
  });
  window.addEventListener('scroll',hide,true);
  window.addEventListener('resize',hide);
})();

/* ---------------------------------------------------------------
   Language switching
   --------------------------------------------------------------- */

function applyLang(){
  document.documentElement.lang=LANG;
  document.title=t('app.title');

  document.querySelectorAll('[data-i18n]').forEach(el=>{
    el.textContent=t(el.dataset.i18n);
  });

  // Accessibility labels are text a screen reader speaks, so they need
  // translating too - they were left in English on the Czech page.
  document.querySelectorAll('[data-i18n-aria]').forEach(el=>{
    el.setAttribute('aria-label',t(el.dataset.i18nAria));
  });
  document.querySelectorAll('[data-i18n-title]').forEach(el=>{
    el.setAttribute('title',t(el.dataset.i18nTitle));
  });

  // The info dots read as "i" to a screen reader otherwise, which says
  // nothing at all; the explanation is the label.
  document.querySelectorAll('.info-dot[data-i18n-tip]').forEach(el=>{
    el.setAttribute('aria-label',t(el.dataset.i18nTip));
  });

  document.querySelectorAll('[data-step]').forEach(btn=>{
    btn.title=t('step.hint');
  });

  document.querySelectorAll('.lang-btn').forEach(btn=>{
    const on=btn.dataset.lang===LANG;
    btn.classList.toggle('active',on);
    btn.setAttribute('aria-pressed',on?'true':'false');
  });
// Sentences with the unit inside them are built, not looked up.
  if(typeof refreshUnitCopy==='function') refreshUnitCopy();

  // Anything rendered from script has to be rebuilt, not just relabelled.
  //
  // In a try because applyLang also runs during start-up, before QUOTA is
  // initialised. typeof does not help here: QUOTA is a const, so touching it
  // early throws from the temporal dead zone rather than reading undefined,
  // and that exception took the whole script down with it.
  try{ renderQuota(); }catch(_){}

  // Redraw so the in-canvas labels pick up the new language.
  draw();
}

function setLang(lang){
  LANG=(lang==='cs')?'cs':'en';
  try{ localStorage.setItem('honza3d-lang',LANG); }catch(_){}
  // A cookie lets the server render the right <html lang> on the next visit.
  try{
    document.cookie='h3d_lang='+LANG+';path=/;max-age=31536000;samesite=lax';
  }catch(_){}
  applyLang();
}

document.querySelectorAll('.lang-btn').forEach(btn=>{
  btn.addEventListener('click',()=>setLang(btn.dataset.lang));
});

(function initLang(){
  // Signed in, the server already resolved the profile language into
  // H3D_LANG - localStorage only speaks for guests on this browser.
  if(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn){
    LANG=(window.H3D_LANG==='cs')?'cs':'en';
    applyLang();
    return;
  }
  let saved=null;
  try{ saved=localStorage.getItem('honza3d-lang'); }catch(_){}

  if(!saved&&window.H3D_LANG){
    saved=window.H3D_LANG;
  }

  LANG=(saved==='cs')?'cs':'en';
  applyLang();
})();

/* ---------------------------------------------------------------
   What's new — signed-in users only, versioned in the database.
   The server emits H3D_NEWS only when this account has not dismissed the
   current revision, so the same release is never shown twice on another
   browser/device. Saving the news in admin increments the revision.
   --------------------------------------------------------------- */
function showNews(){
  if(!window.H3D_NEWS || !(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn))return;
  if(document.querySelector('.news-modal-back'))return;
  const data=window.H3D_NEWS;
  try{
    const localSeen=parseInt(localStorage.getItem('h3d-news-seen-version')||'0',10);
    if(localSeen >= Number(data.version||0)) return;
  }catch(_){}
  const back=document.createElement('div');
  back.className='modal-back news-modal-back';
  const box=document.createElement('div');
  box.className='modal news-modal';
  box.setAttribute('role','dialog'); box.setAttribute('aria-modal','true');
  const tag=document.createElement('div'); tag.className='news-tag';
  tag.textContent=(data.webVersion||'')+(data.version?(' · #'+data.version):'');
  const h=document.createElement('h3'); h.className='news-title'; h.textContent=data.title||t('news.title');
  const list=document.createElement('div'); const lines=String(data.body||'').split(/\r?\n/); let ul=null;
  const flush=()=>{if(ul){list.appendChild(ul);ul=null;}};
  lines.forEach(line=>{const x=line.trim();if(!x){flush();return;}if(x.charAt(0)==='#'){flush();const hh=document.createElement('h4');hh.className='news-h';hh.textContent=x.replace(/^#+\s*/,'');list.appendChild(hh);return;}if(!ul){ul=document.createElement('ul');ul.className='news-list';}const li=document.createElement('li');li.textContent=x;ul.appendChild(li);});
  flush();
  const row=document.createElement('div');row.className='modal-actions';
  const ok=document.createElement('button');ok.type='button';ok.className='btn primary';ok.textContent=t('news.ok');row.appendChild(ok);
  box.append(tag,h,list,row);back.appendChild(box);document.body.appendChild(back);
  const close=()=>{
    document.removeEventListener('keydown',key);

    // Close immediately. The old code awaited the API before removing the
    // modal, so a stalled/failed acknowledgement request left the news window
    // permanently hanging on screen.
    back.remove();

    // Remember the revision locally immediately. This is only a UX fallback;
    // the account database remains the source of truth for other browsers.
    try{localStorage.setItem('h3d-news-seen-version',String(data.version||0));}catch(_){}

    const fd=new FormData();
    fd.append('csrf',window.H3D_CSRF||'');
    fd.append('version',String(data.version||0));

    (async()=>{
      try{
        let r=await fetch('api/news.php',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
        let d=await r.json().catch(()=>null);
        // A stale page can carry an old CSRF token. Refresh it and retry once.
        if(r.status===419 && d && d.csrf_refresh){
          window.H3D_CSRF=d.csrf_refresh;
          fd.set('csrf',window.H3D_CSRF);
          r=await fetch('api/news.php',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
          d=await r.json().catch(()=>null);
        }
        if(r.ok && d && d.ok && d.version){
          try{localStorage.setItem('h3d-news-seen-version',String(d.version));}catch(_){}
        }
      }catch(_){
        // Local acknowledgement already prevents the same browser from
        // reopening the card. The server will be retried on a later release.
      }
    })();
  };
  const key=e=>{if(e.key==='Escape'){e.preventDefault();close();}};
  ok.addEventListener('click',close);back.addEventListener('click',e=>{if(e.target===back)close();});document.addEventListener('keydown',key);ok.focus();
}

/* ---------------------------------------------------------------
   First-run onboarding
   A new visitor picks language, units and theme once. Every choice
   previews live through the existing setters; the same controls stay
   in Preferences afterwards. Tracked per browser so it never nags.
   --------------------------------------------------------------- */
function initOnboarding(){
  // First-run preferences are now part of the guided tour.  Keep the old
  // markup for backwards compatibility, but never open it as a second modal.
  const signed=!!(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn);
  let seen=false;
  try{ seen=localStorage.getItem('honza3d-onboarded')==='1'; }catch(_){ seen=true; }

  if(!signed && !seen){
    // New visitors start from a predictable baseline.  The tour's setup box
    // can immediately change any of these values before the first tool step.
    try{ localStorage.setItem('honza3d-lang','en'); }catch(_){ }    try{ document.cookie='h3d_lang=en;path=/;max-age=31536000;samesite=lax'; }catch(_){ }    if(typeof setLang==='function' && LANG!=='en') setLang('en');    const unit=$('unitSelect');
    if(unit && unit.value!=='mm'){ unit.value='mm'; unit.dispatchEvent(new Event('change')); }
    // The tour is responsible for marking the onboarding as seen once it is
    // completed or skipped.
  }else if(seen){
    const tourSeen=(()=>{ try{return document.cookie.split('; ').some(v=>v.indexOf('h3d_studio_tour_seen=')===0);}catch(_){return false;} })();
    if(tourSeen) showNews();
  }
}

initOnboarding();

/* ---------------------------------------------------------------
   Quota, credits and the account menu
   The server is the authority on all of this; what follows only
   keeps the interface honest between requests.
   --------------------------------------------------------------- */

let QUOTA=window.H3D_QUOTA||{mode:'free',cost:0,retryAfter:0,cooldown:0,balance:0,signedIn:false,confirmSpend:false,freeLeft:0,limit:0};

function formatWait(seconds){
  if(seconds<=0)return '0 s';
  if(seconds<60)return Math.ceil(seconds)+' s';
  if(seconds<3600)return Math.ceil(seconds/60)+' min';
  const h=Math.floor(seconds/3600),m=Math.round((seconds%3600)/60);
  return m?h+' h '+m+' min':h+' h';
}

/** Compact free-export counter that sits next to the Export button in the
 *  header, so the remaining allowance is visible where the user actually
 *  exports - not only in the side card lower down. When the free ones run out
 *  it turns into an upgrade link. Unlimited accounts see nothing. */
function updateExportQuota(){
  const el=$('exportQuota');
  if(!el)return;
  /*
   * A free allowance of zero is "no free exports", not "unlimited".
   *
   * Hiding the line for limit<=0 left the Export popover with a gap where
   * the allowance should be and no number anywhere, on an account that in
   * fact pays a credit for every single export. Only genuinely unlimited
   * accounts have nothing to say here.
   */
  if(QUOTA.unlimited){ el.hidden=true; return; }
  const left=QUOTA.freeLeft|0, lim=QUOTA.limit|0;
  el.hidden=false;
  el.classList.remove('ok','warn','block','upsell');
  el.classList.add(left>0 ? 'ok' : (QUOTA.verified ? 'warn' : 'block'));
  const label='<span class="eq-label">'+t('quota.freeExports')+'</span>';
  if(left>0){
    el.innerHTML=label+'<span class="eq-count">'+t('quota.remaining',left,lim)+'</span>';
    el.removeAttribute('href');
  }else{
    // Out of free exports: offer a way to get more right here.
    el.classList.add('upsell');
    el.innerHTML=label+'<span class="eq-count">'+t('quota.remaining',left,lim)+' · '+t('quota.upgrade')+'</span>';
    el.href=QUOTA.signedIn ? 'account.php' : 'login.php?next=index.php';
  }
}

/** Fills the Credits popover in the rail with this account's numbers. */
function renderCreditsInfo(){
  if(!$('cpBalance'))return;
  const q=QUOTA;
  $('cpBalance').textContent=q.unlimited?'∞':String(q.balance);
  const lim=+q.limit||0;
  // Zero free exports reads as "0 / 0", not as "unlimited". Only an account
  // that really has no ceiling says so.
  $('cpFree').textContent=q.unlimited
    ? t('credits.unlimited')
    : Math.max(0,+q.freeLeft||0)+' / '+lim;
  const reset=+q.windowReset||0;
  const showReset=!q.unlimited&&lim>0&&(+q.freeLeft||0)<lim&&reset>0;
  const rr=$('cpResetRow');
  if(rr){ rr.hidden=!showReset; if(showReset)$('cpReset').textContent=formatWait(reset); }
  const cost=parseFloat(q.cost)||0;
  const cr=$('cpCostRow');
  if(cr){ cr.hidden=!!q.unlimited||cost<=0; if(!cr.hidden)$('cpCost').textContent=q.cost+' '+t('credits.cr'); }
  const note=$('cpNote');
  if(note) note.hidden=!!q.unlimited;
}

/** A visitor has no credit balance, but the rail counter opens this compact
 *  explanation of their remaining free exports and the next reset. */
function renderGuestQuotaInfo(){
  if(QUOTA.signedIn)return;
  const lim=Math.max(0,+QUOTA.limit||0);
  const left=Math.max(0,+QUOTA.freeLeft||0);
  document.querySelectorAll('[data-guest-free]').forEach(el=>{el.textContent=String(left);});
  document.querySelectorAll('[data-guest-limit]').forEach(el=>{el.textContent=String(lim);});
  // Red once there is nothing left, blue while there is.
  const railItem=document.querySelector('.nav-guest-quota');
  if(railItem){
    railItem.classList.toggle('is-empty',left<=0);
    const label=railItem.querySelector('.nav-label>b');
    if(label) label.textContent=t('quota.freeExports');
    railItem.title=t('quota.freeExports')+' '+left+' / '+lim;
  }
  const free=$('guestQuotaFree');
  if(free) free.textContent=lim>0 ? t('quota.remaining',left,lim) : String(left);
  const reset=+QUOTA.windowReset||+QUOTA.retryAfter||0;
  const row=$('guestQuotaResetRow');
  if(row){
    const show=lim>0&&left<lim&&reset>0;
    row.hidden=!show;
    if(show&&$('guestQuotaReset'))$('guestQuotaReset').textContent=formatWait(reset);
  }
  const note=$('guestQuotaNote');
  if(note){
    // Only when it adds something. Saying "1 of 1 free exports left" under a
    // row that already reads "Free exports - 1 of 1" is the same sentence
    // twice, and the popover is four lines tall.
    let msg='';
    if(QUOTA.noFree) msg=t('quota.everyCosts');
    else if(lim>0&&left<lim&&reset>0) msg=t('quota.nextBack',formatWait(reset));
    note.textContent=msg;
    note.hidden=msg==='';
  }
}

function renderQuota(){
  if(typeof syncCreditMark==='function') syncCreditMark();
  updateExportQuota();
  renderCreditsInfo();
  renderGuestQuotaInfo();
  const bar=$('quotaBar');
  if(!bar)return;

  bar.className='quota-card';
  bar.innerHTML='';
  bar.hidden=true;

  // The only case worth saying nothing about is genuinely unlimited free
  // exports: free mode with no window. Everything else - a cost, a wait, a
  // sign-in prompt - has something to tell the user, so it must not be
  // swallowed by a bare "cooldown is zero" check the way it used to be.
  if(QUOTA.mode==='free' && QUOTA.cooldown===0 && !QUOTA.noFree){
    return;
  }

  // How long until one free export returns. The window is a sliding one, so
  // exports come back one at a time as each ages out - never the whole
  // allowance at once, which is what the old "resets in" wording implied.
  const back=formatWait(QUOTA.retryAfter>0 ? QUOTA.retryAfter : QUOTA.windowReset);
  const every=formatWait(QUOTA.cooldown);
  const hasLimit=QUOTA.limit>0;

  // The remaining count leads wherever there is an allowance to speak of, so
  // the number is the first thing read in every state, not only while some
  // are still free.
  const countLine=hasLimit ? t('quota.freeCount',QUOTA.freeLeft,QUOTA.limit) : '';

  let tone='ok', headline='', detail='', link=null;

  if(QUOTA.mode==='free'){
    tone=QUOTA.freeLeft>0 ? 'ok' : 'warn';
    headline=hasLimit ? countLine : t('quota.freeNow');
    // A return is only pending once part of the allowance has been used.
    detail=(hasLimit && QUOTA.freeLeft<QUOTA.limit && back!=='0 s')
      ? t('quota.nextBack',back)
      : '';
  }else if(QUOTA.mode==='login_required'){
    tone='block';
    headline=t('quota.login');
    detail=(hasLimit && QUOTA.cooldown>0) ? t('quota.allowance',QUOTA.limit,every) : '';
    link={href:'login.php?next=index.php',label:t('quota.signin')};
  }else if(QUOTA.mode==='credit'){
    // Free allowance spent, paying with credits from here on. Say the count
    // first (0 of N), then the cost, then when a free one returns.
    tone='warn';
    headline=hasLimit ? countLine : t('quota.everyCosts');
    const bits=[t('quota.costs',QUOTA.cost)];
    if(!QUOTA.noFree && back!=='0 s') bits.push(t('quota.nextBack',back));
    detail=bits.join(' ');
  }else{
    // Blocked: no free export available and no credits to fall back on.
    tone='block';
    if(QUOTA.noFree){
      headline=t('quota.needCredits');
      detail=QUOTA.signedIn ? t('quota.nocredits') : t('quota.everyCosts');
    }else{
      headline=hasLimit ? countLine : t('quota.exhausted');
      const bits=[];
      if(back!=='0 s') bits.push(t('quota.nextBack',back));
      if(QUOTA.signedIn) bits.push(t('quota.nocredits'));
      detail=bits.join(' ');
    }
    link=QUOTA.signedIn
      ? {href:'account.php',label:t('quota.getcredits')}
      : {href:'login.php?next=index.php',label:t('quota.signin')};
  }

  bar.hidden=false;
  bar.classList.add(tone);

  const head=document.createElement('div');
  head.className='quota-head';

  const dot=document.createElement('span');
  dot.className='quota-dot';
  head.appendChild(dot);

  const strong=document.createElement('strong');
  strong.textContent=headline;
  head.appendChild(strong);
  bar.appendChild(head);

  if(detail){
    const p=document.createElement('p');
    p.className='quota-detail';
    p.textContent=detail;
    bar.appendChild(p);
  }

  // The balance is only interesting to somebody who has one.
  if(QUOTA.signedIn && parseFloat(QUOTA.balance)>0){
    const b=document.createElement('p');
    b.className='quota-balance';
    b.textContent=t('quota.balance',QUOTA.balance);
    bar.appendChild(b);
  }

  if(link){
    const a=document.createElement('a');
    a.className='quota-link';
    a.href=link.href;
    a.textContent=link.label;
    bar.appendChild(a);
  }
}

/**
 * The rail's credit item: diamond for unlimited exports, credit mark and a
 * balance for everybody else.
 *
 * Both marks sit in the markup and this only moves the class, so an account
 * whose plan starts (or runs out) while the studio is open changes over on
 * the next quota refresh instead of lying until a reload.
 */
function syncCreditMark(){
  const el=document.querySelector('.nav-credits');
  if(!el)return;
  const unlimited=QUOTA.unlimited===true;
  const left=Math.max(0,+QUOTA.freeLeft||0);
  const limit=Math.max(0,+QUOTA.limit||0);
  const hasFree=!unlimited&&left>0;
  el.classList.toggle('is-unlimited',unlimited);
  el.classList.toggle('has-free',hasFree);
  el.title=unlimited ? 'Neomezené exporty' : (hasFree
    ? 'Volné exporty ' + left + ' / ' + limit
    : 'Kredity ' + String(QUOTA.balance));
  document.querySelectorAll('[data-signed-free]').forEach(x=>{x.textContent=String(left);});
  document.querySelectorAll('[data-signed-limit]').forEach(x=>{x.textContent=String(limit);});
}

function setBalance(n){
  QUOTA.balance=n;
  syncCreditMark();
  // Unlimited accounts (admins, super users, an active plan) never spend
  // credits. The rail drops the number entirely for them - the diamond on
  // its own is the message - so there is nothing to update there.
  if(QUOTA.unlimited)return;
  const shown=String(n);
  // Both the header chip and the credits item in the left rail follow along.
  ['creditValue','navCreditValue'].forEach(id=>{
    const el=$(id);
    if(!el || el.textContent===shown) return;
    el.textContent=shown;
    el.classList.toggle('is-inf',QUOTA.unlimited===true);
    const chip=el.closest('.credit-chip')||el.closest('.nav-credits');
    if(chip){
      chip.classList.remove('changed');
      void chip.offsetWidth;          // restart the animation
      chip.classList.add('changed');
    }
  });
  document.querySelectorAll('[data-credit-rail]').forEach(el=>{el.textContent=shown;});
}

/** Re-read the allowance from the server. */
async function refreshQuota(){
  try{
    const res=await fetch('api/quota.php',{headers:{'Accept':'application/json'}});
    if(!res.ok)return;
    const q=await res.json();
    QUOTA=q;
    setBalance(q.balance);
    renderQuota();
  }catch(_){}
}

// The countdown only needs to be roughly right, so a 15 s tick is plenty
// and costs nothing.
setInterval(()=>{
  if(QUOTA.retryAfter>0){
    QUOTA.retryAfter=Math.max(0,QUOTA.retryAfter-15);
    if(QUOTA.retryAfter===0){
      refreshQuota();
    }else{
      renderQuota();
    }
  }
},15000);

/* ---- Account menu ---- */
(function initAccountMenu(){
  const wrap=$('accountWrap'),btn=$('accountBtn'),menu=$('accountMenu');
  if(!wrap||!btn||!menu)return;

  const open=()=>{menu.classList.add('open');btn.setAttribute('aria-expanded','true')};
  const close=()=>{menu.classList.remove('open');btn.setAttribute('aria-expanded','false')};

  wrap.addEventListener('mouseenter',open);
  wrap.addEventListener('mouseleave',close);
  btn.addEventListener('click',e=>{
    e.stopPropagation();
    menu.classList.contains('open')?close():open();
  });
  document.addEventListener('click',close);
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
})();

renderQuota();


/* ---------------------------------------------------------------------------
 * Studio shell: left nav rail, canvas tool strip, bottom status bar and the
 * project share/load pair. All driven from the mockup redesign.
 * ------------------------------------------------------------------------- */
(function initStudioShell(){
  const $q=s=>document.querySelector(s);

  /* ---- Bottom status bar ---- */
  let _sig='', _changed=Date.now();
  window.updateStatusBar=function(total,used){
    const g=grid();
    if(total==null){ total=g.c*g.r; used=boxes.reduce((s,b)=>s+b.w*b.h,0); }
    const d=$('sbDims');
    if(d) d.textContent=`${formatUnit(getMM('dw'))} × ${formatUnit(getMM('dd'))} × ${formatUnit(getMM('dh'))} ${currentUnit()}`;
    if($('sbBoxes')) $('sbBoxes').textContent=boxes.length;
    const pct=total?Math.round(used/total*100):0;
    if($('sbCells')) $('sbCells').textContent=`${used} / ${total} (${pct}%)`;
    // Only a real change to dimensions/grid/boxes stamps a new "saved" time;
    // a plain re-render (resize) must not keep resetting it.
    const sig=[getMM('dw'),getMM('dd'),getMM('dh'),g.c,g.r,
      boxes.map(b=>b.x+','+b.y+','+b.w+','+b.h
).join(';')].join('|');
    if(sig!==_sig){ _sig=sig; _changed=Date.now(); }
    renderSaved();
  };
  function renderSaved(){
    const el=$('sbSaved'); if(!el)return;
    const s=(Date.now()-_changed)/1000;
    el.textContent = s<45 ? t('status.now') : (s<3600 ? t('status.minsAgo',Math.max(1,Math.round(s/60))) : t('status.hoursAgo',Math.round(s/3600)));
    const c=$('sbCheck'); if(c) c.hidden=false;
  }
  setInterval(renderSaved,30000);

  /* ---- Settings flyouts driven by the icon rail ----
     The old always-on side panels are relocated into floating popovers so the
     canvas gets the whole width. Each rail icon toggles the popover holding its
     controls; only one is open at a time. Moving the panels with appendChild
     keeps every id and every listener intact - the control code bound them
     earlier in this same script. */
  const layer=$('flyoutLayer');
  const flyouts={};
  if(layer){
    document.querySelectorAll('[data-panel]').forEach(panel=>{
      const key=panel.dataset.panel;
      const fly=document.createElement('section');
      fly.className='flyout'; fly.dataset.panel=key; fly.hidden=true;
      const close=document.createElement('button');
      close.className='flyout-close'; close.type='button'; close.setAttribute('aria-label','Zavřít');
      close.innerHTML='<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';
      close.addEventListener('click',closeFlyout);
      fly.appendChild(close);
      fly.appendChild(panel);
      panel.hidden=false;         // the wrapper owns visibility now
      layer.appendChild(fly);
      flyouts[key]=fly;
    });
  }
  let openKey=null;
  let supportExplicitlyOpened=false;
  window.__h3dOpenFlyoutKey=()=>openKey;
  function flyBackdrop(on){
    let bd=document.getElementById('flyoutBackdrop');
    if(on){
      if(!bd){ bd=document.createElement('div'); bd.id='flyoutBackdrop'; bd.className='flyout-backdrop'; bd.addEventListener('click',closeFlyout); document.body.appendChild(bd); }
      bd.hidden=false;
    }else if(bd){ bd.hidden=true; }
  }
  // The help ("Návod") opens as a full-screen panel with the live limits;
  // every other popover stays a small flyout beside its icon.
  function renderHelpLimits(){
    const el=document.getElementById('helpLimits'); if(!el)return;
    const L=window.H3D_LIMITS||{}, cs=(document.documentElement.lang==='cs');
    const per=s=> (typeof formatWait==='function'?formatWait(s):Math.round((s||0)/60)+' min');
    const rows=[];
    rows.push([cs?'Nepřihlášený':'Visitor',
      L.guestExport ? (L.freeGuest||0)+(cs?' export / ':' export / ')+per(L.cooldownGuest)
                    : (cs?'jen po přihlášení':'sign in first')]);
    rows.push([cs?'Přihlášený':'Signed in',
      (L.freeUser||0)+(cs?' zdarma / ':' free / ')+per(L.cooldownUser)+
      (parseFloat(L.creditCost)>0?(cs?', pak ':', then ')+L.creditCost+(cs?' kr.':' cr'):'')]);
    if(parseFloat(L.signupBonus)>0) rows.push([cs?'Bonus za registraci':'Signup bonus', L.signupBonus+(cs?' kreditů':' credits')]);
    // Only when it is actually switched on: a row saying "0 a day" is
    // worse than no row, because it reads as a promise that failed.
    if(parseFloat(L.dailyCredits)>0){
      const cap=parseFloat(L.dailyCap)>0?(cs?', strop ':', cap ')+L.dailyCap:'';
      rows.push([cs?'Denně přibude':'Added daily', L.dailyCredits+(cs?' kreditů':' credits')+cap]);
    }
    rows.push([cs?'Max. boxů':'Max boxes', String(L.maxBoxes||0)]);
    rows.push([cs?'Max. mřížka':'Max grid', (L.maxCells||15)+' × '+(L.maxCells||15)]);
    el.innerHTML='<div class="hl-title">'+(cs?'Aktuální limity':'Current limits')+'</div>'+
      rows.map(r=>'<div class="hl-row"><span>'+r[0]+'</span><b>'+r[1]+'</b></div>').join('');
  }
  window.renderHelpLimits=renderHelpLimits;

  function closeFlyout(){
    if(openKey&&flyouts[openKey]){
      flyouts[openKey].hidden=true;
      flyouts[openKey].classList.remove('flyout-full');
      if(openKey==='messageAdmin'){
        supportExplicitlyOpened=false;
        const supportPanel=flyouts[openKey].querySelector('.h3d-support-panel');
        if(supportPanel){
          supportPanel.hidden=true;
          supportPanel.setAttribute('aria-hidden','true');
        }
      }
    }
    openKey=null;
    window.__h3dOpenFlyoutKey=()=>openKey;
    flyBackdrop(false);
    document.querySelectorAll('.nav-item[data-flyout]').forEach(b=>b.classList.remove('active'));
  }
  function openFlyout(key,anchor){
    const fly=flyouts[key];
    if(!fly)return;
    if(key==='messageAdmin') supportExplicitlyOpened=true;
    if(openKey===key){ closeFlyout(); return; }
    closeFlyout();
    fly.hidden=false;
    // messageAdmin is wrapped by the same flyout system as the other panels.
    // Its inner panel must always be visible when the wrapper opens.
    if(key==='messageAdmin'){
      const supportPanel=fly.querySelector('.h3d-support-panel');
      if(supportPanel && supportExplicitlyOpened) {
        supportPanel.hidden=false;
        supportPanel.removeAttribute('aria-hidden');
        // Let the support module know that its flyout has actually opened.
        window.dispatchEvent(new Event('h3dSupportOpened'));
      }
    }
    openKey=key;
    window.__h3dOpenFlyoutKey=()=>openKey;
    // Help is fullscreen; the support Chat stays a normal compact flyout.
    const full=(key==='howto');
    fly.classList.toggle('flyout-full',full);
    flyBackdrop(full);
    if(full){
      // Full-screen panels own their viewport geometry in CSS.
      fly.style.top=''; fly.style.right=''; fly.style.bottom=''; fly.style.left='';
      fly.style.width=''; fly.style.height='';
      renderHelpLimits();
    }else if(window.matchMedia('(min-width:621px)').matches){
      const bodyEl=$q('.studio-body');
      if(bodyEl&&anchor){
        const bb=bodyEl.getBoundingClientRect(), ab=anchor.getBoundingClientRect();
        const h=fly.offsetHeight||320;
        let top=ab.top-bb.top;
        top=Math.max(12,Math.min(top, bb.height-h-12));
        fly.style.top=top+'px'; fly.style.bottom='auto';
      }
    }else{ fly.style.top=''; fly.style.bottom=''; }
    document.querySelectorAll('.nav-item[data-flyout]').forEach(b=>b.classList.toggle('active',b.dataset.flyout===key));
  }
  // Chat must never open by itself on page load. It is opened only by an
  // explicit click on the Chat icon.
  if(flyouts.messageAdmin){
    flyouts.messageAdmin.hidden=true;
    flyouts.messageAdmin.classList.remove('flyout-full');
    const initialSupport=flyouts.messageAdmin.querySelector('.h3d-support-panel');
    if(initialSupport){
      initialSupport.hidden=true;
      initialSupport.setAttribute('aria-hidden','true');
    }
  }
  window.openFlyout=openFlyout; window.closeFlyout=closeFlyout;

  // Locked-feature card: a short description of what the feature does plus a
  // sign-in button, shown where the real popover would open.
  function openLocked(key,anchor){
    if(!layer)return;
    let fly=flyouts.__locked;
    if(!fly){
      fly=document.createElement('section');
      fly.className='flyout flyout-locked'; fly.hidden=true;
      layer.appendChild(fly); flyouts.__locked=fly;
    }
    const names={
      print:t('nav.print'),layout:t('nav.layout'),inspector:t('nav.inspector'),load:t('nav.load'),
      walls:t('tool.walls'),
      messageAdmin:(document.documentElement.lang==='cs' ? 'Chat s živým kolegou' : 'Live colleague chat')
    };
    const descs={
      print:t('lock.print'),layout:t('lock.layout'),inspector:t('lock.inspector'),load:t('lock.load'),walls:t('lock.walls'),
      messageAdmin:(document.documentElement.lang==='cs'
        ? 'Chat s živým kolegou je dostupný po ověření e-mailového účtu.'
        : 'Live colleague chat is available after you verify your email address.')
    };
    fly.innerHTML='<button class="flyout-close" type="button" aria-label="Zavřít"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'
      +'<div class="lock-info"><span class="lock-info-ic"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Z"/></svg></span>'
      +'<div class="lock-info-t"></div><div class="lock-info-sub"></div><p></p>'
      +'<a class="btn primary full" href="login.php?next=index.php"></a></div>';
    fly.querySelector('.lock-info-t').textContent=names[key]||'';
    const needsVerification=window.H3D_AUTHENTICATED===true && window.H3D_VERIFIED===false;
    fly.querySelector('.lock-info-sub').textContent=needsVerification
      ? (document.documentElement.lang==='cs' ? 'Ověř e-mail' : 'Verify your email')
      : t('lock.title');
    fly.querySelector('.lock-info p').textContent=needsVerification
      ? (document.documentElement.lang==='cs'
          ? 'Tato funkce je dostupná až po ověření e-mailového účtu.'
          : 'This feature is available after you verify your email address.')
      : (descs[key]||'');
    const lockBtn=fly.querySelector('.lock-info .btn');
    lockBtn.textContent=needsVerification
      ? (document.documentElement.lang==='cs' ? 'Otevřít účet' : 'Open account')
      : t('nav.signin');
    lockBtn.href=needsVerification ? 'account.php' : 'login.php?next=index.php';
    fly.querySelector('.flyout-close').addEventListener('click',closeFlyout);
    closeFlyout();
    fly.hidden=false; openKey='__locked';
    if(window.matchMedia('(min-width:621px)').matches && anchor){
      const bb=$q('.studio-body').getBoundingClientRect(), ab=anchor.getBoundingClientRect();
      const top=Math.max(12,Math.min(ab.top-bb.top, bb.height-(fly.offsetHeight||240)-12));
      fly.style.top=top+'px'; fly.style.bottom='auto';
    }else{ fly.style.top=''; fly.style.bottom=''; }
    if(anchor) anchor.classList.add('active');
  }

  document.querySelectorAll('.nav-item[data-flyout]').forEach(btn=>{
    const lab=btn.querySelector('.nav-label');
    if(lab) btn.title=lab.textContent;
    btn.addEventListener('click',e=>{
      e.stopPropagation();
      // A locked feature shows a short "what this does" card with a sign-in
      // button instead of opening the real controls.
      if(btn.classList.contains('guest-lock')){ openLocked(btn.dataset.flyout,btn); return; }
      openFlyout(btn.dataset.flyout,btn);
    });
  });
  document.querySelectorAll('.nav-item[data-nav]').forEach(btn=>{
    const lab=btn.querySelector('.nav-label');
    if(lab) btn.title=lab.textContent;
    btn.addEventListener('click',e=>{
      e.stopPropagation();
      if(btn.classList.contains('guest-lock')){ openLocked(btn.dataset.lockkey||btn.dataset.nav,btn); return; }
      const k=btn.dataset.nav;
      if(k==='export'){ const et=$('exportTrigger'); if(et){ et.click(); try{ et.focus(); }catch(_){} } }
      else if(k==='share'){ shareProject(); }
    });
  });
  document.addEventListener('click',e=>{
    if(openKey && !e.target.closest('.flyout') && !e.target.closest('.nav-item')) closeFlyout();
  });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeFlyout(); });

  // Double-mode rail: icon-only, or widened with the category labels. The
  // choice is remembered so it survives a reload.
  const rail=$('studioNav'), navToggle=$('navToggle');

  // Low-height protection: browser zoom and short windows can leave the
  // vertical rail without enough real space for all its controls. Measure the
  // actual rail/body geometry instead of guessing from window.innerHeight,
  // then switch to a horizontal icon strip. Opening that strip uses a
  // two-column menu so every labelled item remains reachable.
  function syncCompactNav(){
    if(!rail) return;
    const body=rail.closest('.studio-body');
    if(!body || !window.matchMedia('(min-width:621px)').matches){
      rail.classList.remove('compact-nav');
      if(body) body.classList.remove('compact-nav-body');
      return;
    }

    const wasCompact=rail.classList.contains('compact-nav');
    const wasExpanded=rail.classList.contains('expanded');

    // Once a short viewport has switched to the horizontal rail, expanding it
    // must stay in the compact two-column mode. Re-measuring the normal
    // vertical rail here used to remove compact-nav immediately after the
    // click, leaving only the icons/toggle visible at the top.
    if(wasCompact && wasExpanded){
      rail.classList.add('compact-nav');
      body.classList.add('compact-nav-body');
      return;
    }

    // Temporarily restore the normal vertical rail so scrollHeight represents
    // the full menu we actually need to fit.
    if(wasCompact){
      rail.classList.remove('compact-nav');
      body.classList.remove('compact-nav-body');
      void rail.offsetHeight;
    }

    const needsCompact=rail.scrollHeight > rail.clientHeight + 4;
    if(needsCompact){
      rail.classList.add('compact-nav');
      body.classList.add('compact-nav-body');
    }else{
      rail.classList.remove('compact-nav');
      body.classList.remove('compact-nav-body');
    }

    // Keep the current expanded/collapsed choice; sync the translated label
    // after the geometry switch so the toggle remains accessible.
    if(wasExpanded) rail.classList.add('expanded');
  }

  let compactNavRaf=0;
  const queueCompactNav=()=>{
    cancelAnimationFrame(compactNavRaf);
    compactNavRaf=requestAnimationFrame(syncCompactNav);
  };
  if(rail){
    queueCompactNav();
    window.addEventListener('resize',queueCompactNav,{passive:true});
    if(window.ResizeObserver){
      const compactRO=new ResizeObserver(queueCompactNav);
      const body=rail.closest('.studio-body');
      if(body) compactRO.observe(body);
      compactRO.observe(rail);
    }
  }
  if(rail&&navToggle){
    const syncLabel=()=>{
      const lab=navToggle.querySelector('.nav-label');
      const ex=rail.classList.contains('expanded');
      const key=ex?'nav.collapse':'nav.expand';
      if(lab){ lab.setAttribute('data-i18n',key); lab.textContent=t(key); }
      // The header brand mirrors the rail: just the logo when collapsed, the
      // full "Drawer Organizer Studio" name once the menu is expanded.
      document.body.classList.toggle('nav-expanded', ex);
    };
    // The rail always starts collapsed - expanding is a per-visit choice
    // and is deliberately not remembered across reloads or sign-ins.
    syncLabel();
    navToggle.addEventListener('click',()=>{
      // The toggle is sticky at the rail bottom. Browsers may otherwise
      // adjust the scroll container while its label appears, which makes all
      // icons visibly jump up and then settle back down.
      const scrollTop=rail.scrollTop;
      // A focused sticky button asks some browsers to scroll its parent into
      // view once the rail width changes. It has just performed its action,
      // so retaining keyboard focus here buys nothing and causes the jump.
      try{ navToggle.blur(); }catch(_){}
      rail.classList.toggle('expanded');
      syncLabel();
      const restoreScroll=()=>{ rail.scrollTop=scrollTop; };
      requestAnimationFrame(()=>{
        restoreScroll();
        requestAnimationFrame(restoreScroll);
      });
      // Width and label transitions finish later than the click. Let their
      // final layout settle first, then return the rail to the exact view
      // the user had before opening it.
      window.setTimeout(restoreScroll,360);
      // Re-measure after the expanded/collapsed state changes: a rail that
      // fit vertically while collapsed may need the compact two-column mode
      // as soon as its labels are opened.
      queueCompactNav();
    });
  }

  // "Save project" inside the Export popover (signed-in only) downloads the
  // layout as JSON - the old Share action, merged in here.
  const sp=$('shareProjectBtn');
  if(sp) sp.addEventListener('click',()=>{ shareProject(); closeFlyout(); });
  const shareBackdrop=$('shareLinkBackdrop');
  const shareClose=$('shareLinkClose');
  const closeSharePopup=()=>{ if(shareBackdrop){ shareBackdrop.hidden=true; document.body.classList.remove('share-link-open'); } };
  if(shareClose) shareClose.addEventListener('click',closeSharePopup);
  if(shareBackdrop) shareBackdrop.addEventListener('click',e=>{ if(e.target===shareBackdrop) closeSharePopup(); });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape' && shareBackdrop && !shareBackdrop.hidden) closeSharePopup(); });

  /* ---- Canvas view: free zoom + pan ---- */
  const svg=$('svg');
  const wrap=svg&&svg.closest('.canvasWrap');

  function refreshZoomLabel(){
    const v=$('zoomVal'); if(v) v.textContent=Math.round(VIEWSTATE.z*100)+'%';
  }
  // Zoom keeping the given screen point (canvas coords) where it is - the
  // spot under the cursor or between two fingers stays put.
  function zoomAt(nz,px,py){
    nz=Math.max(0.3,Math.min(4,nz));
    const rect=svg.getBoundingClientRect();
    const cx=(px!==undefined?px:rect.width/2);
    const cy=(py!==undefined?py:rect.height/2);
    // Screen point p maps to board space via ox=cX-boardW/2+tx; keeping p
    // fixed while z changes means scaling (p - centre - t) by nz/z.
    const k=nz/VIEWSTATE.z;
    const midX=VIEW_W/2, midY=56+(VIEW_H-56-92)/2;
    VIEWSTATE.tx=cx-midX-((cx-midX-VIEWSTATE.tx)*k);
    VIEWSTATE.ty=cy-midY-((cy-midY-VIEWSTATE.ty)*k);
    VIEWSTATE.z=nz;
    refreshZoomLabel();
    draw();
  }
  function setView(z,tx,ty){
    VIEWSTATE.z=Math.max(0.3,Math.min(4,z));
    VIEWSTATE.tx=tx; VIEWSTATE.ty=ty;
    refreshZoomLabel();
    draw();
  }
  if($('zoomIn')) $('zoomIn').addEventListener('click',()=>zoomAt(VIEWSTATE.z*1.15));
  if($('zoomOut')) $('zoomOut').addEventListener('click',()=>zoomAt(VIEWSTATE.z/1.15));
  // Corners icon: show the whole design (100 % is exactly the fitted view).
  if($('zoomReset')) $('zoomReset').addEventListener('click',()=>setView(1,0,0));
  // Crosshair: keep the zoom, just bring the board back to the middle.
  if($('viewCenter')) $('viewCenter').addEventListener('click',()=>setView(VIEWSTATE.z,0,0));

  // Wheel zooms toward the cursor; the page itself must never zoom or
  // scroll while the pointer is over the canvas.
  if(wrap){
    wrap.addEventListener('wheel',e=>{
      e.preventDefault();
      const r=svg.getBoundingClientRect();
      const f=e.deltaY<0?1.12:1/1.12;
      zoomAt(VIEWSTATE.z*f,e.clientX-r.left,e.clientY-r.top);
    },{passive:false});
  }

  /* ---- Tool modes: pointer, hand, 3D ---- */
  // Free mode on purpose: with the pointer you can still pan by dragging
  // empty canvas and zoom with the wheel, and holding space borrows the
  // hand. The hand tool is mainly for touch, where dragging a box and
  // dragging the canvas would otherwise fight each other.
  window.H3D_TOOL='select';
  let spaceHeld=false;
  function setTool(name){
    window.H3D_TOOL=name;
    document.querySelectorAll('.ct-modes .ct-btn[data-tool]').forEach(b=>{
      b.classList.toggle('active',b.dataset.tool===name);
    });
    if(wrap)wrap.classList.toggle('tool-pan',name==='pan');

    // Reaching for the hand means you are done with that box: its handles
    // sit right where you want to grab the canvas, so the selection goes
    // away instead of fighting the drag.
    if(name==='pan'&&typeof selected!=='undefined'&&selected!==null){
      selected=null;
      draw();
    }
  }
  document.querySelectorAll('.ct-modes .ct-btn[data-tool]').forEach(b=>{
    b.addEventListener('click',()=>setTool(b.dataset.tool));
  });
  if($('wallModeBtn')){
    $('wallModeBtn').addEventListener('click',e=>{
      e.preventDefault();
      e.stopPropagation();
      if(!verifiedIn()){
        openLocked('walls',$('wallModeBtn'));
        return;
      }
      // A workspace saved by an older version can contain a Free shape while
      // its mode flag still says Walls. Clicking the Wall icon then repairs
      // that inconsistent state by offering the conversion instead of merely
      // toggling away from Walls again.
      setWallMode(WALLMODE&&boxes.some(isPoly)?true:!WALLMODE);
    });
    paintWallMode();
  }
  if($('freeModeBtn')){
    $('freeModeBtn').addEventListener('click',e=>{
      e.preventDefault();
      e.stopPropagation();
      if(!verifiedIn()){
        openLocked('walls',$('freeModeBtn'));
        return;
      }
      setWallMode(false);
    });
  }
  document.querySelectorAll('[data-wall-mode]').forEach(btn=>{
    btn.addEventListener('click',e=>{
      e.preventDefault();e.stopPropagation();
      if(!signedIn())return;
      setWallMode(btn.dataset.wallMode==='walls');
    });
  });
  document.querySelectorAll('[data-wall-grid]').forEach(btn=>{
    btn.addEventListener('click',async e=>{
      e.preventDefault();e.stopPropagation();
      if(!signedIn())return;
      await setWallGrid(+btn.dataset.wallGrid);
    });
  });
  paintWallGrid();
  document.addEventListener('keydown',e=>{
    if(e.code!=='Space'||spaceHeld)return;
    const tag=(e.target.tagName||'').toLowerCase();
    if(tag==='input'||tag==='select'||tag==='textarea'||e.target.isContentEditable)return;
    e.preventDefault();
    spaceHeld=true;
    if(wrap)wrap.classList.add('tool-pan');
  });
  document.addEventListener('keyup',e=>{
    if(e.code!=='Space')return;
    spaceHeld=false;
    if(wrap)wrap.classList.toggle('tool-pan',window.H3D_TOOL==='pan');
  });
  window.h3dPanMode=()=>spaceHeld||window.H3D_TOOL==='pan';

  // Free pan: dragging any empty part of the canvas moves the view at every
  // zoom level. Two fingers pinch-zoom and pan together on touch.
  (function initPan(){
    if(!wrap)return;
    const pointers=new Map();
    let pinch=null;
    let panning=false,sx=0,sy=0,stx=0,sty=0;
    const grabbable=t=>{
      if(!t)return true;
      const d=t.dataset||{};
      if(d.id||d.ghost||d.add)return false;
      const c=t.classList;
      if(c&&(c.contains('handle')||c.contains('resize-handle')||c.contains('edge-grip')||c.contains('resize-corner')))return false;
      return true;
    };
    wrap.addEventListener('pointerdown',e=>{
      pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});
      if(pointers.size===2){
        // Second finger: whatever drag was running becomes a pinch.
        panning=false; drag=null;
        const [a,b]=[...pointers.values()];
        pinch={d:Math.hypot(a.x-b.x,a.y-b.y),z:VIEWSTATE.z,
               mx:(a.x+b.x)/2,my:(a.y+b.y)/2,tx:VIEWSTATE.tx,ty:VIEWSTATE.ty};
        return;
      }
      // With the hand (or space held) everything drags the canvas, boxes
      // included; with the pointer only empty space does.
      if(!window.h3dPanMode()&&!grabbable(e.target))return;
      panning=true; sx=e.clientX; sy=e.clientY; stx=VIEWSTATE.tx; sty=VIEWSTATE.ty;
      wrap.classList.add('panning');
      try{ wrap.setPointerCapture(e.pointerId); }catch(_){}
    });
    wrap.addEventListener('pointermove',e=>{
      const p=pointers.get(e.pointerId);
      if(p){ p.x=e.clientX; p.y=e.clientY; }
      if(pinch&&pointers.size>=2){
        const [a,b]=[...pointers.values()];
        const d=Math.hypot(a.x-b.x,a.y-b.y);
        const mx=(a.x+b.x)/2,my=(a.y+b.y)/2;
        VIEWSTATE.tx=pinch.tx+(mx-pinch.mx);
        VIEWSTATE.ty=pinch.ty+(my-pinch.my);
        const r=svg.getBoundingClientRect();
        zoomAt(pinch.z*(d/Math.max(1,pinch.d)),mx-r.left,my-r.top);
        return;
      }
      if(!panning)return;
      VIEWSTATE.tx=stx+(e.clientX-sx);
      VIEWSTATE.ty=sty+(e.clientY-sy);
      draw();
    });
    const end=e=>{
      if(e&&e.pointerId!==undefined)pointers.delete(e.pointerId);
      if(pointers.size<2)pinch=null;
      panning=false; wrap.classList.remove('panning');
    };
    wrap.addEventListener('pointerup',end);
    wrap.addEventListener('pointercancel',end);
  })();

  document.querySelectorAll('.ct-modes .ct-btn[data-tool]').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const tool=btn.dataset.tool;
      if(tool==='grid'){ const on=svg.classList.toggle('hide-grid'); btn.classList.toggle('active',on); return; }
      if(tool==='labels'){ const on=svg.classList.toggle('hide-labels'); btn.classList.toggle('active',on); return; }
      if(tool==='fit'){ setView(1,0,0); return; }
      if(tool==='cube'){ openFlyout('inspector', document.querySelector('.nav-item[data-flyout="inspector"]')); }
      if(tool==='code'){
        try{ if(navigator.clipboard) navigator.clipboard.writeText(projectJSON()); if(typeof showExportNote==='function') showExportNote(t('share.saved')); }catch(_){}
      }
      // select/pan/pencil/cube/code share one exclusive highlight; grid &
      // labels are independent on/off toggles handled above. The hand used
      // to be left out here, so setTool marked it active and this ran a
      // moment later and took the highlight straight back off.
      document.querySelectorAll('.ct-modes .ct-btn[data-tool]').forEach(b=>{
        if(b.dataset.tool!=='grid' && b.dataset.tool!=='labels') b.classList.remove('active');
      });
      if(tool==='select'||tool==='pan'||tool==='pencil'||tool==='cube'||tool==='code') btn.classList.add('active');
    });
  });

  /* ---- Project share (download JSON) / load (import JSON) ---- */
  window.projectJSON=function(){
    const g=grid();
    return JSON.stringify({
      v:1, unit:'mm',
      dw:getMM('dw'),dd:getMM('dd'),dh:getMM('dh'),
      gap:getMM('gap'),wall:getMM('wall'),bottom:getMM('bottom'),radius:getMM('radius'),
      cols:g.c,rows:g.r,
      boxes:boxes.map(b=>({
        x:b.x,y:b.y,w:b.w,h:b.h,
        ...(Number.isInteger(b.colorSlot)?{colorSlot:b.colorSlot}:{}),
        ...(isPoly(b)?{cells:cellsOf(b).map(c=>({x:c.x,y:c.y}))}:{}),
        ...(hasWalls(b)?{walls:b.walls.slice()}:{}),
        ...(hasMidWalls(b)?{midWalls:b.midWalls.slice()}:{}),
        ...(hasHalfWalls(b)?{halfWalls:b.halfWalls.slice()}:{} )
      }))
    });
  };
  /**
   * Puts a link on the clipboard and says whether it got there.
   *
   * The modern API needs a secure origin and can be refused outright, so the
   * old hidden-textarea trick stays as the fallback; between them they cover
   * about everything, but neither is guaranteed, hence the answer.
   */
  async function copyShareLink(url){
    if(!url)return false;
    try{
      if(navigator.clipboard&&navigator.clipboard.writeText){
        await navigator.clipboard.writeText(url);
        return true;
      }
    }catch(_){}
    try{
      const ta=document.createElement('textarea');
      ta.value=url; ta.setAttribute('readonly','');
      ta.style.position='fixed'; ta.style.opacity='0';
      document.body.appendChild(ta); ta.select();
      const ok=document.execCommand('copy');
      ta.remove();
      return !!ok;
    }catch(_){ return false; }
  }

  if($('shareLinkCopy')) $('shareLinkCopy').addEventListener('click',async()=>{
    const value=$('shareLinkValue');
    const url=value?value.textContent.trim():'';
    const copied=await copyShareLink(url);
    const status=$('shareLinkStatus');
    if(status) status.textContent=copied?t('share.popup.copied'):t('share.popup.copyHint');
    if(copied&&typeof showExportNote==='function') showExportNote(t('share.created'));
  });

  window.shareProject=async function(){
    if(!window.H3D_QUOTA || !window.H3D_QUOTA.signedIn || !window.H3D_QUOTA.verified){
      window.location.href='login.php?next=index.php';
      return;
    }
    try{
      const fd=new FormData();
      fd.append('project',projectJSON());
      fd.append('csrf',window.H3D_CSRF||'');
      const r=await fetch('api/share.php',{method:'POST',body:fd,credentials:'same-origin',cache:'no-store'});
      const d=await r.json();
      if(!r.ok || !d.ok || !d.url) throw new Error('share');
      const copied=await copyShareLink(d.url);
      const value=$('shareLinkValue');
      const backdrop=$('shareLinkBackdrop');
      if(value) value.textContent=d.url;
      // Say what actually happened. The clipboard is refused often enough -
      // an insecure origin, a permission prompt, an embedded browser - and
      // "copied to the clipboard" over an empty clipboard sends people
      // hunting for a link they never got.
      const status=$('shareLinkStatus');
      if(status) status.textContent=copied?t('share.popup.copied'):t('share.popup.copyHint');
      if(backdrop){ backdrop.hidden=false; document.body.classList.add('share-link-open'); }
      if(copied&&typeof showExportNote==='function') showExportNote(t('share.created'));
    }catch(_){
      if(typeof showExportError==='function') showExportError(t('share.error'));
    }
  };

  /*
   * A thumbnail of a design, drawn from the project itself.
   *
   * The drawer keeps its own proportions, so a long shallow one looks like
   * one, and each box is filled cell by cell with the outline drawn only
   * along the edges that have no neighbour of the same box - which is what
   * makes an L read as one piece rather than as three squares.
   */
  function drawSharePreview(p,cv){
    const ctx=cv.getContext&&cv.getContext('2d');
    if(!ctx)return;
    const cols=Math.max(1,p.cols|0), rows=Math.max(1,p.rows|0);
    const W=cv.width, H=cv.height, pad=16;
    const dw=(+p.dw>0)?+p.dw:cols, dd=(+p.dd>0)?+p.dd:rows;
    const s=Math.min((W-2*pad)/dw,(H-2*pad)/dd);
    const ox=(W-dw*s)/2, oy=(H-dd*s)/2, bw=dw*s, bh=dd*s;
    const css=getComputedStyle(document.body);
    const line=(css.getPropertyValue('--line')||'').trim()||'#d8dee8';
    const muted=(css.getPropertyValue('--muted')||'').trim()||'#8b93a1';

    ctx.clearRect(0,0,W,H);
    ctx.strokeStyle=line; ctx.lineWidth=2;
    ctx.strokeRect(ox,oy,bw,bh);

    const cw=bw/cols, ch=bh/rows;
    ctx.strokeStyle=muted; ctx.globalAlpha=.2; ctx.lineWidth=1;
    ctx.beginPath();
    for(let i=1;i<cols;i++){ const x=ox+cw*i; ctx.moveTo(x,oy); ctx.lineTo(x,oy+bh); }
    for(let j=1;j<rows;j++){ const y=oy+ch*j; ctx.moveTo(ox,y); ctx.lineTo(ox+bw,y); }
    ctx.stroke();
    ctx.globalAlpha=1;

    (Array.isArray(p.boxes)?p.boxes:[]).forEach((b,idx)=>{
      const slot=Number.isInteger(b.colorSlot)
        ? ((b.colorSlot%BOX_HUES.length)+BOX_HUES.length)%BOX_HUES.length
        : ((idx*7+3)%BOX_HUES.length+BOX_HUES.length)%BOX_HUES.length;
      const hue=BOX_HUES[slot];
      const cells=(Array.isArray(b.cells)&&b.cells.length)
        ? b.cells
        : (()=>{ const a=[]; for(let y=0;y<(b.h|0);y++)for(let x=0;x<(b.w|0);x++)a.push({x:(b.x|0)+x,y:(b.y|0)+y}); return a; })();
      const at=new Set(cells.map(c=>(c.x|0)+','+(c.y|0)));
      ctx.fillStyle='hsla('+hue+',44%,63%,.62)';
      cells.forEach(c=>{ ctx.fillRect(ox+(c.x|0)*cw,oy+(c.y|0)*ch,cw,ch); });
      ctx.strokeStyle='hsl('+hue+',40%,42%)'; ctx.lineWidth=1.6;
      ctx.beginPath();
      cells.forEach(c=>{
        const x=ox+(c.x|0)*cw, y=oy+(c.y|0)*ch, cx=c.x|0, cy=c.y|0;
        if(!at.has(cx+','+(cy-1))){ ctx.moveTo(x,y); ctx.lineTo(x+cw,y); }
        if(!at.has((cx+1)+','+cy)){ ctx.moveTo(x+cw,y); ctx.lineTo(x+cw,y+ch); }
        if(!at.has(cx+','+(cy+1))){ ctx.moveTo(x,y+ch); ctx.lineTo(x+cw,y+ch); }
        if(!at.has((cx-1)+','+cy)){ ctx.moveTo(x,y); ctx.lineTo(x,y+ch); }
      });
      ctx.stroke();
    });
  }

  /*
   * The design waiting to be accepted. It is held here rather than applied,
   * because opening somebody's link used to wipe the board without asking.
   */
  let sharedPending=null;

  function closeSharePreview(){
    const bd=$('sharePreviewBackdrop');
    if(bd){ bd.hidden=true; document.body.classList.remove('share-link-open'); }
  }

  /*
   * The left-hand board and what taking the design would cost, both kept
   * current while the person decides.
   *
   * The board fills in after the page loads - a signed-in workspace arrives
   * on its own schedule, and the shared design usually beats it - so reading
   * it once when the dialog opens gets the answer wrong. draw() calls this
   * too, which covers the workspace arriving and anything else that changes
   * the board while the dialog is up.
   */
  function updateSharePreviewWarning(){
    const warn=$('sharePreviewWarn');
    if(warn){
      const mine=(typeof boxes!=='undefined'&&boxes.length>0);
      // An empty drawer has nothing to lose, and saying so is friendlier
      // than warning about the destruction of nothing.
      warn.textContent=mine?t('share.preview.warn'):t('share.preview.empty');
      warn.classList.toggle('is-warning',mine);
    }
    const mineCv=$('sharePreviewCanvasMine');
    if(mineCv){
      try{ drawSharePreview(JSON.parse(projectJSON()),mineCv); }
      catch(_){ }
    }
  }
  window.updateSharePreviewWarning=updateSharePreviewWarning;

  function openSharePreview(p){
    const bd=$('sharePreviewBackdrop');
    if(!bd){ applyProject(p); return; }   // markup missing: better than losing the link
    sharedPending=p;
    const cv=$('sharePreviewCanvas');
    if(cv) drawSharePreview(p,cv);
    const facts=$('sharePreviewFacts');
    if(facts){
      const size=fmtNum(fromMM(+p.dw||0))+' × '+fmtNum(fromMM(+p.dd||0))+' × '
                +fmtNum(fromMM(+p.dh||0))+' '+currentUnit();
      facts.textContent=t('share.preview.summary',size,(Array.isArray(p.boxes)?p.boxes.length:0),p.cols|0,p.rows|0);
    }
    updateSharePreviewWarning();
    bd.hidden=false;
    document.body.classList.add('share-link-open');
    const take=$('sharePreviewTake');
    if(take) take.focus();
  }

  if($('sharePreviewClose')) $('sharePreviewClose').addEventListener('click',()=>{ sharedPending=null; closeSharePreview(); });
  if($('sharePreviewX')) $('sharePreviewX').addEventListener('click',()=>{ sharedPending=null; closeSharePreview(); });
  if($('sharePreviewBackdrop')) $('sharePreviewBackdrop').addEventListener('click',e=>{
    if(e.target===$('sharePreviewBackdrop')){ sharedPending=null; closeSharePreview(); }
  });
  document.addEventListener('keydown',e=>{
    const bd=$('sharePreviewBackdrop');
    if(e.key==='Escape'&&bd&&!bd.hidden){ sharedPending=null; closeSharePreview(); }
  });
  if($('sharePreviewTake')) $('sharePreviewTake').addEventListener('click',()=>{
    if(!sharedPending)return;
    const p=sharedPending;
    sharedPending=null;
    closeSharePreview();
    applyProject(p);
    /*
     * Take the key out of the address bar. Left there, a refresh - or the
     * browser restoring the tab tomorrow - would hand the same design over
     * again and throw away whatever was built on top of it in between.
     */
    try{
      const u=new URL(window.location.href);
      u.searchParams.delete('share');
      history.replaceState(null,'',u.pathname+(u.search||'')+u.hash);
    }catch(_){}
    if(typeof showExportNote==='function') showExportNote(t('share.preview.taken'));
  });

  async function loadSharedProjectFromUrl(){
    const key=window.H3D_SHARE_KEY;
    if(!key)return;
    try{
      const r=await fetch('api/share.php?key='+encodeURIComponent(key),{credentials:'same-origin',cache:'no-store'});
      const d=await r.json();
      if(!r.ok || !d.ok || !d.project) throw new Error('share-load');
      openSharePreview(d.project);
    }catch(_){
      if(typeof showExportError==='function') showExportError(t('share.error'));
    }
  }

  function downloadProjectJson(){
    try{
      const blob=new Blob([projectJSON()],{type:'application/json'});
      const url=URL.createObjectURL(blob);
      const a=document.createElement('a');
      a.href=url; a.download='organizer-layout.json';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(()=>URL.revokeObjectURL(url),1200);
      if(typeof showExportNote==='function') showExportNote(t('share.saved'));
    }catch(_){ if(typeof showExportError==='function') showExportError(t('share.error')); }
  }

  loadSharedProjectFromUrl();

  function setDimMM(id,mm){
    if(typeof mm!=='number'||!isFinite(mm))return;
    const v=fromMM(mm);
    setMM(id,mm);
    const el=$(id); if(el) el.value=fmtNum(v);
    const sl=$(id+'Slider'); if(sl){ sl.value=v; updateRange(id+'Slider'); }
    updateDimensionLabel(id,mm);
  }
  // Restore both ordinary rectangles and the saved L/T/free-shape cell list.
  // The list is rechecked here as well as on the server, so a damaged local
  // preference cannot turn into an invalid canvas state.
  function pushLoadedBox(b,g){
    const x=b.x|0,y=b.y|0,w=Math.max(1,b.w|0),h=Math.max(1,b.h|0);
    const loadedCells=[];
    const cellSeen=new Set();
    if(Array.isArray(b.cells))b.cells.forEach(c=>{
      const cx=c&&Number.isInteger(+c.x)?+c.x:NaN;
      const cy=c&&Number.isInteger(+c.y)?+c.y:NaN;
      const key=cx+','+cy;
      if(cx>=0&&cy>=0&&cx<g.c&&cy<g.r&&!cellSeen.has(key)){
        cellSeen.add(key);loadedCells.push({x:cx,y:cy});
      }
    });
    const walls=Array.isArray(b.walls)
      ? b.walls.filter(k=>typeof k==='string'&&/^[vh]:-?\d+,-?\d+$/.test(k))
      : null;
    const midWalls=Array.isArray(b.midWalls)
      ? b.midWalls.filter(k=>k==='m:h'||k==='m:v')
      : null;
    const halfWalls=Array.isArray(b.halfWalls)
      ? b.halfWalls.filter(k=>typeof k==='string'&&/^[vh]:\d+(?:,\d+)?$/.test(k))
      : null;
    const useCells=loadedCells.length>0&&cellsConnected(loadedCells);
    if(useCells||(x>=0&&y>=0&&x+w<=g.c&&y+h<=g.r)){
      const nb=useCells
        ? {id:nextId++,x:0,y:0,w:1,h:1,cells:loadedCells}
        : {id:nextId++,x,y,w,h};
      if(Number.isInteger(b.colorSlot)&&b.colorSlot>=0&&b.colorSlot<BOX_HUES.length)nb.colorSlot=b.colorSlot;
      if(useCells)syncPolyBounds(nb);
      if(walls&&walls.length)nb.walls=walls;
      // Upgrade the former one-off centre divider into the same half-grid
      // representation used by every new wall.
      if(midWalls&&midWalls.length){
        const migrated=[];
        if(midWalls.includes('m:v')&&nb.w%2===1)for(let y2=0;y2<nb.h*2;y2++)migrated.push('v:'+nb.w+','+y2);
        if(midWalls.includes('m:h')&&nb.h%2===1)for(let x2=0;x2<nb.w*2;x2++)migrated.push('h:'+x2+','+nb.h);
        if(migrated.length)nb.halfWalls=migrated;
      }
      if(halfWalls&&halfWalls.length){
        const migrated=[];
        halfWalls.forEach(k=>{
          if(k.includes(',')){migrated.push(k);return;}
          const [type,nText]=k.split(':'),n=+nText;
          if(type==='v')for(let y2=0;y2<nb.h*2;y2++)migrated.push('v:'+n+','+y2);
          else for(let x2=0;x2<nb.w*2;x2++)migrated.push('h:'+x2+','+n);
        });
        nb.halfWalls=[...(nb.halfWalls||[]),...migrated];
      }
      sanitizeWalls(nb);
      boxes.push(nb);
    }
  }

  function applyProject(o){
    if(!o||typeof o!=='object')return;
    setDimMM('dw',o.dw); setDimMM('dd',o.dd); setDimMM('dh',o.dh);
    ['gap','wall','bottom','radius','outer'].forEach(k=>{ if(typeof o[k]==='number'&&isFinite(o[k])){ setMM(k,o[k]); const el=$(k); if(el) el.value=formatUnit(getMM(k)); } });
    // Limit follows the loaded floor, but a project that arrives too short is
    // left as it is: the Inspector reports it and Fix repairs it.
    enforceMinHeight(false);
    if(o.cols) $('cols').value=Math.min(MAXCELLS,Math.max(1,o.cols|0));
    if(o.rows) $('rows').value=Math.min(MAXCELLS,Math.max(1,o.rows|0));
    if($('bw')){ options($('bw'),+$('cols').value); options($('bd'),+$('rows').value); }
    if(typeof syncRandomOptions==='function') syncRandomOptions();
    const g=grid();
    boxes.length=0; selected=null;
    if(Array.isArray(o.boxes)){
      o.boxes.forEach(b=>pushLoadedBox(b,g));
    }
    normaliseWallGrid();
    // A loaded L/T shape is intentionally incompatible with divider editing.
    // Keep the shape and leave its editor available instead of flattening it.
    if(boxes.some(isPoly)&&wallModeOn())setWallMode(false,false,false);
    draw();
  }

  /* ---- Per-account studio preferences, saved to the server ---- */
  // v23: the initial generator/draw pass is NOT allowed to save for a signed-in
  // account. On F5/Ctrl+F5 the server workspace must be loaded first; otherwise
  // the startup generator can race the GET and overwrite the user's real design.
  let _workspaceBootstrapped=false;
  function collectPrefs(){
    const g=grid();
    return {
      dw:getMM('dw'),dd:getMM('dd'),dh:getMM('dh'),
      gap:getMM('gap'),wall:getMM('wall'),bottom:getMM('bottom'),radius:getMM('radius'),
      cols:g.c,rows:g.r,
      maxPrintW:($('maxPrintW')?getMM('maxPrintW'):0)||0,
      maxPrintD:($('maxPrintD')?getMM('maxPrintD'):0)||0,
      lang:LANG,
      unit:currentUnit(),
      // Keep wall mode and the radius as independent workspace settings.
      wallMode:WALLMODE?1:0,
      wallStash:WALLSTASH===null?0:WALLSTASH,
      wallGrid:WALLGRID,
      boxes:boxes.map(b=>({
        x:b.x,y:b.y,w:b.w,h:b.h,
        ...(Number.isInteger(b.colorSlot)?{colorSlot:b.colorSlot}:{}),
        ...(isPoly(b)?{cells:cellsOf(b).map(c=>({x:c.x,y:c.y}))}:{}),
        ...(hasWalls(b)?{walls:b.walls.slice()}:{}),
        ...(hasMidWalls(b)?{midWalls:b.midWalls.slice()}:{}),
        ...(hasHalfWalls(b)?{halfWalls:b.halfWalls.slice()}:{} )
      }))
    };
  }
  function applyBoxesPref(o){
    if(!o||!Array.isArray(o.boxes))return false;
    const g=grid();
    boxes.length=0; selected=null;
    o.boxes.forEach(b=>pushLoadedBox(b,g));
    return true;
  }
  let _prefSig='', _prefTimer=null, _workspaceUpdatedAt=0;
  let _prefSavePending=false;
  window.__H3D_PREF_SAVE_PENDING=()=>_prefSavePending;
  // Applying a saved workspace redraws the canvas a few times while fields
  // and wall mode are restored. Those intermediate, empty states are never
  // user edits and must never be queued as a newer server workspace.
  let _applyingStudioPrefs=false;
  // The account icon doubles as a save indicator: green head = everything is
  // saved to your profile, red = the last save failed.
  function setSaveStatus(state){
    // Both the credits chip and the account item link to account.php; the
    // save indicator belongs on the account (user) icon, not the credits one.
    const a=document.querySelector('.studio-nav .nav-item[href="account.php"]:not(.nav-credits)');
    if(!a)return;
    a.classList.remove('save-ok','save-err','save-wait');
    if(state) a.classList.add('save-'+state);
  }
  window.setSaveStatus=setSaveStatus;
  function workspacePayload(sig){
    const prefs=JSON.parse(sig);
    prefs.workspaceUpdatedAt=_workspaceUpdatedAt||Date.now();
    return JSON.stringify(prefs);
  }
  function cacheWorkspace(payload){
    try{
      localStorage.setItem(workspaceStorageKey(),JSON.stringify({
        at:_workspaceUpdatedAt||Date.now(), prefs:JSON.parse(payload)
      }));
    }catch(_){}
  }
  function sendWorkspaceNow(){
    const signed=!!(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn);
    if(!signed||!_workspaceBootstrapped||!_prefSig||!navigator.sendBeacon)return;
    const payload=workspacePayload(_prefSig);
    cacheWorkspace(payload);
    const data=new FormData();
    data.append('csrf',window.H3D_CSRF||'');
    data.append('prefs',payload);
    navigator.sendBeacon('api/prefs.php',data);
  }
  window.savePrefsDebounced=function(force=false){
    const signed=!!(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn);
    // During the initial signed-in bootstrap, draw(), resize handlers, the
    // generator and Inspector/Repair may all run. None of those startup
    // renders are user edits and none may overwrite the server workspace.
    if(signed&&!_workspaceBootstrapped)return;
    if(signed&&_applyingStudioPrefs)return;
    const sig=JSON.stringify(collectPrefs());
    if(sig===_prefSig&&!force)return;
    _prefSig=sig;
    // Every saved state gets a strictly newer timestamp. Previously the
    // timestamp was assigned only on the first edit; two requests generated
    // during one editing session therefore looked identical to PHP and could
    // arrive out of order, letting an older grid overwrite the newer one.
    const now=Date.now();
    _workspaceUpdatedAt=Math.max(now,_workspaceUpdatedAt+1);
    const payload=workspacePayload(sig);
    cacheWorkspace(payload);
    clearTimeout(_prefTimer);
    _prefSavePending=true;
    _prefTimer=setTimeout(()=>{
      if(signed){
        setSaveStatus('wait');
        fetch('api/prefs.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf='+encodeURIComponent(window.H3D_CSRF||'')+'&prefs='+encodeURIComponent(payload)})
          .then(async r=>{
            let d=null; try{d=await r.json();}catch(_){ }
            if(d&&d.csrf_refresh&&!r.ok){
              window.H3D_CSRF=d.csrf_refresh;
              return fetch('api/prefs.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'csrf='+encodeURIComponent(window.H3D_CSRF||'')+'&prefs='+encodeURIComponent(payload)
              }).then(async rr=>{
                let rd=null; try{rd=await rr.json();}catch(_){}
                if(rr.ok){
                  if(rd&&Number.isFinite(Number(rd.workspaceRevision))){
                    window.H3D_WORKSPACE_REVISION=Math.max(Number(window.H3D_WORKSPACE_REVISION||0),Number(rd.workspaceRevision));
                  }
                  setSaveStatus('ok');
                }else setSaveStatus('err');
              });
            }
            if(r&&r.ok){
              if(d&&Number.isFinite(Number(d.workspaceRevision))){
                window.H3D_WORKSPACE_REVISION=Math.max(Number(window.H3D_WORKSPACE_REVISION||0),Number(d.workspaceRevision));
              }
              setSaveStatus('ok');
            }else setSaveStatus('err');
          })
          .catch(()=>setSaveStatus('err'))
          .finally(()=>{_prefSavePending=false;});
      }else{
        try{ localStorage.setItem('h3d_studio',sig); }catch(_){ }
        _prefSavePending=false;
      }
    },800);
  };
  window.applyStudioPrefs=function(o){
    if(!o||typeof o!=='object')return;
    _applyingStudioPrefs=true;
    try{
    // Language from the saved workspace wins over the browser preference.
    if((o.lang==='cs'||o.lang==='en') && o.lang!==LANG && typeof setLang==='function'){
      setLang(o.lang);
    }
    if((o.unit==='mm'||o.unit==='in') && $('unitSelect') && o.unit!==currentUnit()){
      $('unitSelect').value=o.unit;
      persistUnit(o.unit);
      refreshUnitUI();
    }
    setDimMM('dw',o.dw); setDimMM('dd',o.dd); setDimMM('dh',o.dh);
    ['gap','wall','bottom','radius','outer'].forEach(k=>{
      if(typeof o[k]==='number'&&isFinite(o[k])){
        setMM(k,o[k]); const el=$(k); if(el) el.value=formatUnit(getMM(k));
        const sl=$(k+'Slider'); if(sl){ sl.value=getMM(k); updateRange(k+'Slider'); }
      }
    });
    // Same as a loaded project: take the limit from the saved floor, but do
    // not quietly rewrite a saved height that falls under it.
    enforceMinHeight(false);
    if(o.cols){ $('cols').value=Math.min(MAXCELLS,Math.max(1,o.cols|0)); setReadout('colsVal',$('cols').value); updateRange('cols'); }
    if(o.rows){ $('rows').value=Math.min(MAXCELLS,Math.max(1,o.rows|0)); setReadout('rowsVal',$('rows').value); updateRange('rows'); }
    ['maxPrintW','maxPrintD'].forEach(k=>{
      if(typeof o[k]==='number'&&isFinite(o[k])&&$(k)){
        setMM(k,o[k]); $(k).value=fmtNum(fromMM(getMM(k)));
        const sl=$(k+'Slider'); if(sl){ sl.value=fromMM(getMM(k)); updateRange(k+'Slider'); }
      }
    });
    if(typeof updateRadiusPreview==='function') updateRadiusPreview();
    if(typeof syncRandomOptions==='function') syncRandomOptions();
    if($('bw')){ options($('bw'),+$('cols').value); options($('bd'),+$('rows').value); }
    // Restore the layout before wall mode. Turning wall mode off deliberately
    // removes dividers, and doing that against the temporary empty startup
    // canvas used to queue an empty workspace save before these boxes arrived.
    applyBoxesPref(o);
    // Wall mode and radius are independent; restore both as saved.
    if(typeof o.wallStash==='number'&&isFinite(o.wallStash)&&o.wallStash>0) WALLSTASH=o.wallStash;
    // Restore the chosen divider detail with the layout.  A saved 2:1
    // layout must not be silently flattened back to 1:1 during page load.
    WALLGRID=o.wallGrid===2?2:1;
    normaliseWallGrid();
    if(o.wallMode===undefined){
      // Never saved: this is how the studio starts, so wall mode goes on.
      setWallMode(true,false);
    }else{
      setWallMode(!!o.wallMode,true,false);
    }
    paintWallMode();
    paintWallGrid();
    // The workspace may have changed only in its boxes while wall mode stays
    // the same. setWallMode() intentionally returns early in that case, so
    // explicitly redraw here after applying the remote/local workspace.
    if(typeof draw==='function') draw();
    // Seed the signature so applying the saved values does not immediately
    // trigger a redundant save back to the server.
    _prefSig=JSON.stringify(collectPrefs());
    _workspaceUpdatedAt=Number(o.workspaceUpdatedAt)||0;
    }finally{
      _applyingStudioPrefs=false;
    }
  };

// A signed-in user lands on a fresh generated layout every time; a visitor
// starts with an empty drawer so nothing is prefilled for them.
if(window.H3D_QUOTA && window.H3D_QUOTA.signedIn && $('randomMinW')){
  // Load the account workspace. A synchronous browser copy is the first
  // line of defence against Ctrl+F5/page close racing an in-flight request;
  // the server copy is then used unless the local copy is newer.
  const readLocalWorkspace=()=>{
    try{
      const raw=localStorage.getItem(workspaceStorageKey());
      const saved=raw?JSON.parse(raw):null;
      return saved&&saved.prefs&&typeof saved.prefs==='object'?saved:null;
    }catch(_){ return null; }
  };
  const cachedWorkspace=readLocalWorkspace();
  fetch('api/prefs.php',{headers:{'Accept':'application/json'}})
    .then(r=>r.ok?r.json():null)
    .then(d=>{
      const serverPrefs=d&&d.prefs&&typeof d.prefs==='object'?d.prefs:null;
      const localAt=Number(cachedWorkspace&&cachedWorkspace.at)||0;
      const serverAt=Number(serverPrefs&&serverPrefs.workspaceUpdatedAt)||0;
      // The browser snapshot is written synchronously at every edit. When a
      // refresh races a just-finished server save, equal timestamps still
      // belong to this browser's newest visible workspace, not to a blank
      // startup response.
      const useLocal=!!(cachedWorkspace&&localAt>serverAt);
      const p=useLocal?cachedWorkspace.prefs:serverPrefs;

      // Applying a stored workspace must never be allowed to kill the whole
      // studio boot. A malformed legacy preference, a partial migration, or
      // a bad divider entry is recoverable: keep the canvas alive, fall back
      // to the browser snapshot when possible, and only then use the current
      // in-memory startup state. Most importantly, never generate a new
      // layout merely because applying an existing account workspace failed.
      let appliedWorkspace=false;
      if(p&&typeof applyStudioPrefs==='function') {
        try{ applyStudioPrefs(p); appliedWorkspace=true; }catch(_){
          if(cachedWorkspace&&cachedWorkspace.prefs&&cachedWorkspace.prefs!==p){
            try{ applyStudioPrefs(cachedWorkspace.prefs); appliedWorkspace=true; }catch(__){}
          }
        }
      }else if(!p&&typeof setWallMode==='function'){
        try{ setWallMode(true); }catch(_){}
      }

      // From this point onward draw()/generator/repair are real user actions
      // and are allowed to persist. Before this flag flips, startup rendering
      // is deliberately write-protected.
      _workspaceBootstrapped=true;
      // An empty array is a deliberate saved layout after Delete layout - it
      // is just as much a project as one with boxes and must never regenerate
      // itself after Shift/Ctrl+F5.
      const hasSavedLayout=!!(p&&Array.isArray(p.boxes));
      if(hasSavedLayout){
        draw();
      }else if(!p){
        // Brand-new account only: generate the initial layout. An existing
        // account without boxes must never be replaced by the startup generator.
        randomLayout();
      }else{
        draw();
      }
      // Whatever came back from the account is the starting point, not the
      // first step - undo must not walk back into an empty drawer that the
      // user never made.
      historyReset();
      if(typeof setSaveStatus==='function') setSaveStatus('ok');
      // The browser copy won a race with the server; replay it once so the
      // account catches up without asking the user to edit anything again.
      if(useLocal&&typeof savePrefsDebounced==='function') savePrefsDebounced(true);
    })
    .catch(()=>{
      // A temporary network failure must never replace a project with a new
      // random design. Restore the local copy when present, but never let a
      // damaged cache keep the boot gate closed. The canvas has already had
      // its protected startup paint above, so even a failed GET leaves the
      // Studio visible instead of hiding the grid.
      if(cachedWorkspace&&typeof applyStudioPrefs==='function') {
        try{ applyStudioPrefs(cachedWorkspace.prefs); }catch(_){}
      }
      _workspaceBootstrapped=true;
      try{ draw(); historyReset(); }catch(_){}
      if(typeof setSaveStatus==='function') setSaveStatus('err');
    });
}else{
  /*
   * Visitor: the studio opens on the house defaults.
   *
   * Drawer 250 x 250 x 50, wall 2, floor 2, radius 4, gap 0.4, a 4 x 4 grid
   * and a fresh generated layout - the numbers in the markup, every single time. Only
   * how the page looks (theme, language, unit) is remembered, because that
   * is a preference rather than a design.
   *
   * Bringing the last visit's drawer back was worse than it sounds: without
   * an account there is nothing to tell you those numbers are yours from
   * last time, so a drawer somebody measured once quietly became the
   * starting point for every design after it.
   */
  Promise.resolve().then(()=>{
    try{
      const s=localStorage.getItem('h3d_studio');
      if(s && typeof applyStudioPrefs==='function'){
        const o=JSON.parse(s);
        const look={};
        ['theme','lang','unit'].forEach(k=>{ if(o[k]!==undefined) look[k]=o[k]; });
        applyStudioPrefs(look);
      }
    }catch(_){}
    /*
     * And an empty grid. The generator is reserved for an account, so a
     * visitor used to be handed a drawer filled edge to edge with 1 x 1
     * boxes - sixteen of them to clear away before the first one of your own.
     * An empty grid with a + in every cell says "start here" instead.
     */
    boxes.length=0;
    selected=null;
    draw();
  });
}



  // Ctrl+F5 and closing the browser do not wait for the normal 800ms save.
  // Keep an immediate browser snapshot and hand the same payload to PHP.
  window.addEventListener('pagehide',sendWorkspaceNow);

  try{ updateStatusBar(); }catch(_){}
})();


/* ---------------------------------------------------------------------------
 * Sign-in dialog
 *
 * Posting to login.php with ajax=1 keeps the drawer layout on screen: sending
 * somebody to a separate page to sign in throws away whatever they had
 * arranged, which is the whole reason this exists.
 * ------------------------------------------------------------------------- */
(function(){
  const back=$('signinBackdrop');
  if(!back)return;

  const email=$('signinEmail');
  const pass=$('signinPass');
  const err=$('signinError');
  const btnLabel=$('signinSubmitLabel');
  let lastFocus=null;

  function open(){
    lastFocus=document.activeElement;
    back.hidden=false;
    err.hidden=true;
    setTimeout(()=>email.focus(),0);
  }

  function close(){
    back.hidden=true;
    pass.value='';
    if(lastFocus&&lastFocus.focus)lastFocus.focus();
  }

  /*
   * A signed-in reply reloads the page, so the spinner has to stay up until
   * the new page paints: clearing it on success would flash the form back
   * for a moment and read as a failure.
   */
  function showSigninBusy(on){
    const box=$('signinBusy');
    if(box)box.hidden=!on;
    if(btnLabel)btnLabel.hidden=on;
  }

  function fail(msg){
    err.textContent=msg;
    err.hidden=false;
    pass.select();
  }

  async function submit(){
    if(!email.value||!pass.value){
      fail(t('signin.missing'));
      return;
    }

    const btn=$('signinSubmit');
    btn.disabled=true;
    showSigninBusy(true);

    try{
      const body=new URLSearchParams({
        ajax:'1',
        csrf:window.H3D_CSRF||'',
        email:email.value,
        password:pass.value,
      });

      let res=await fetch('login.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body,
        credentials:'same-origin'
      });

      // A login form can remain open after its PHP session has expired.
      // login.php returns a fresh token in that case; retry once without
      // making the user reload the page or type the password again.
      let data=null;
      try{ data=await res.json(); }catch(_){ location.href='login.php'; return; }

      if(data.csrf_refresh){
        window.H3D_CSRF=data.csrf_refresh;
        body.set('csrf',data.csrf_refresh);
        res=await fetch('login.php',{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body,
          credentials:'same-origin'
        });
        try{ data=await res.json(); }catch(_){ location.href='login.php'; return; }
      }

      if(data.ok){
        // Deliberately not clearing the spinner: the navigation below
        // replaces the page and a flash of the form would look like a
        // failure rather than a success.
        // Reloading is what picks up the session everywhere at once: the
        // credit chip, the generator panel and the quota card are all
        // rendered server side.
        location.href=data.next||window.H3D_SIGNIN_NEXT||location.pathname;
        return;
      }
      fail(data.error||t('signin.failed'));
    }catch(_){
      fail(t('signin.failed'));
    }finally{
      btn.disabled=false;
      showSigninBusy(false);
    }
  }

  $('signinSubmit').addEventListener('click',submit);
  $('signinCancel').addEventListener('click',close);
  back.addEventListener('click',e=>{ if(e.target===back)close(); });
  document.addEventListener('keydown',e=>{ if(e.key==='Escape'&&!back.hidden)close(); });
  [email,pass].forEach(el=>el.addEventListener('keydown',e=>{ if(e.key==='Enter')submit(); }));

  // Any link that would send you to the login page opens the dialog instead.
  document.querySelectorAll('a[href^="login.php"]').forEach(a=>{
    // These lead somewhere the dialog cannot go.
    if(a.dataset.i18n==='signin.full'||a.dataset.i18n==='signin.forgot')return;
    a.addEventListener('click',e=>{ e.preventDefault(); open(); });
  });

  if(window.H3D_SIGNIN)open();
})();
