Diablo II Graphics convertor


Author: The Dude from novapolis.net
Licence: GNU GENERAL PUBLIC LICENSE Version 3


1. BRIEF DESCRIPTION
  Diablo II Graphics convertor is web based application for displaying Diablo II monster graphics and converting them to PNG and GIF.
  Application is written in PHP.

  You can see example here http://diablo.novapolis.net/diablographics/


2. REQUIREMENTS
  To run Diablo II Graphics convertor you will need web server with PHP installed.


3. INSTALLATION
  a) Copy all the files to chosen folder on your webserver.
  b) You will need few Diablo II data to properly show properties and images.
     Those are monster data files, txt data, TBL string data and palettes. You can extract them from Diablo II MPQ files.
     You can download TXT, TBL and palettes here http://diablo.novapolis.net/diablographics/DiabloGraphicsData.zip
     However you will have to obtain monster data yourself, best from Diablo II MPQ archives.
     This guide does not cover how to do it, but basically you have to get some MPQ extractor
     like http://www.zezula.net/cz/mpq/download.html and extract monster data.
  c) In fun/config.php file set paths to the required data. You can leave it as that if you copy data to these folders,
     or you can set your own.


4. USAGE
  Open via browser the folder of the application and index.php.

  GUI is not the most intuitive here.
  You will see select button and ok button.
  From select pick a monster to convert, for example very nice is "succubuswitch3 - Stygian Fury".
  Click Ok once.
  A little info will be shown.
  First is list of available configurations of moves.
  Second is a form, where you can set desired configuration.

  Let's continue with the example
  a) With the witch, first select Act5 to get proper pallete.
  b) Next remap table, that's color mode, select Random.
  c) Directon at which the witch will be heading. Select E for east.
  d) Action, you can see what the letters stand in the upper list. E.g. A1 is attack1, and so on. Select WL for walk.
  e) Weapons. If creature has available weapons, you can choose one. Witch has none, so leave HTH (hand to hand)
  f) Now there can be several rows for each monster parts like torso, weapon, helmet, and so on.
     Each row enables you to select remap index (8 for Monster pallete, 30 for random) and other options.
     Select 22 for witch.
  g) Click the same OK button again and you get nice violet witch flying east.

  You can explore other modes and monsters.


5. NOTES
  Sometimes monster has incomplete data or some special data or ugly image, like Diablo himself.
  Incomplete data Diablo II issue, and all you can do is delete the monster folder, so it does not show in the selection list.
  Other issues are not resolved by this application, at least not now. It would require more work and study, something that
  goes beyong my initial goals.

  If you are interested in more about Diablo II graphics, you should read the "Extracting Diablo II Animations.pdf" by Paul Siramy.


6. CREDITS
  Diablo II®
  BLIZZARD ENTERTAINMENT®

  Big thanks Paul Siramy for his extensive work on Diablo II graphics format, which helped me greatly with this
  http://paul.siramy.free.fr/
  http://paul.siramy.free.fr/_divers2/mtmptutcmap/
  http://paul.siramy.free.fr/_divers2/tmptutcmap/


  And also some links with guides and useful information about Diablo II, thanks to all those authors who made them
  https://d2mods.info/forum/downloadsystemcat?id=23
  https://www.diablofans.com/forums/site-related/diablowiki/3711-dr-tester-guide
  https://github.com/krisives/d2s-format
  https://d2mods.info/forum/viewtopic.php?f=8&t=9011
  https://github.com/Zutatensuppe/DiabloInterface
  https://user.xmission.com/~trevin/DiabloIIv1.09_File_Format.shtml
  https://d2mods.info/forum/viewtopic.php?p=248164#248164
  https://d2mods.info/resources/infinitum/tut_files/dcc_tutorial/



  This application also uses 3rd party code for making animated GIFS
  Thanks to Sybio (Clément Guillemain) for his GifCreator
  https://github.com/Sybio/GifCreator

