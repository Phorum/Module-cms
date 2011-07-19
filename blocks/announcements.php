<?php
// This Phorum CMS block makes it possible to include the list of
// announcements (as provided by the "Announcements" Phorum module)
// on any page. Use the following code in the template:
//
// {HOOK "cms_block" "announcements"}

if (!empty($PHORUM['mods']['announcements']))
{
    $backup = $PHORUM['mod_announcements'];

    if (!empty($cms_parameters[0]))
    {
      $PHORUM['mod_announcements']['only_show_unread'] = FALSE;
      $PHORUM['mod_announcements']['number_to_show']   = $cms_parameters[0];
      $PHORUM['mod_announcements']['days_to_show']     = 0;
    }

    $PHORUM['mod_announcements']['pages'][phorum_page] = TRUE;
    phorum_setup_announcements();
    phorum_show_announcements();

    $PHORUM['mod_announcements'] = $backup;
}
else
{
    print '[CMS error: The Announcements Phorum module is not activated]';
}

