<?php

$Definition['mastermindplugin.explanation'] = "Een plugin om mastermind mee te spelen.

Het heeft de volgende kleuren: Rood zwart geel groen blauw oranje wit roze en x (als gat).";
$Definition["Red green black blue"] = "rood groen zwart blauw";
$Definition["Create a new code for people to guess with the specified colours."] = "Maak een nieuwe code aan voor mensen om te raden met de genoemde kleuren.";
$Definition["Create a random code of length X."] = "Maak een nieuwe random code aan van lengte X.";
$Definition["black green yellow blue"] = "zwart groen geel blauw";
$Definition["Make a guess on either a quoted code or the latest code."] = "Doe een gok op een gequote code of de laatstgeposte code.";
$Definition["Earlier guesses"]="Eerdere gokken";
$color_translation = ["rood" => 0, "geel" => 1, "oranje" => 2, "groen" => 3, "blauw" => 4, "zwart" => 5, "roze" => 6, "wit" => 7];
foreach ($color_translation as $name => $value) {
    $Definition["mastermind.$name"] = $value;
}
unset($color_translation);
