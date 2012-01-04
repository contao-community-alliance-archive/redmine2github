<?php

/**
 * redmine2github
 * Copyright (C) 2012 MEN AT WORK
 *
 * PHP version 5
 * @copyright MEN AT WORK 2012
 * @author Andreas Isaak <cms@men-at-work.de>, Stefan Heimes <cms@men-at-work.de>
 * @package redmine2github
 * @license LGPL
 * @filesource
 */

class Redmine2Github
{

    /**
     * CSV Informations
     */
    protected $strCSVPath = "export.csv";
    protected $strCSVDelimiter = ";";

    /**
     * Url for repo
     * User with right for adding labels and edit issues
     */
    protected $strRepoURL = "https://api.github.com/repos/:user/:repo";
    protected $strRepoUser = ":user";
    protected $strRepoPassword = ":pass";

    /**
     * Messages
     */
    protected $strOriginalAutor = "*--- Originally created by %s on %s.*";
    protected $strMilestoneVersion = "Version %s";

    /**
     * Array with user informations.
     * First on is fallback if we have an unknown user.
     */
    protected $arrUser = array(
        "Octo Cat" => array(
            "login" => "octocat",
            "password" => "github"
        ),
    );

    /**
     * Array with label information
     */
    protected $arrAcceptedLabels = array(
        "Accepted" => array("name" => "Accepted", "color" => "dddddd"),
        "Completed" => array("name" => "Completed", "color" => "dddddd"),
        "Closed" => array("name" => "Completed", "color" => "dddddd"),
        "Defect" => array("name" => "Defect", "color" => "e10c02"),
        "Feature" => array("name" => "Feature", "color" => "02e10c"),
    );

    /**
     * Key assoc for csv file
     */
    protected $arrCSVKeys = array(
        "id",
        "Status",
        "Project",
        "Tracker",
        "Priority",
        "Topic",
        "Assigned",
        "Category",
        "Target Version",
        "Autor",
        "Beginning",
        "Due Date",
        "Finished",
        "Estimated time",
        "Parent task",
        "Created",
        "Updated",
        "Installed extensions",
        "Version",
        "Description",
    );
    protected $arrSearch = array(
        '/`/',
        '/([^ ]+)@([a-z0-9\.-]+\.[a-z]{2,6})/i',
        '/\n*<pre>\n?<code class="([^"]+)">\n?/',
        '/\n*<pre>\n?/',
        '/\n?<\/code>\n?<\/pre>\n*/',
        '/\n?<\/pre>\n*/',
        '/\[(http[^\]]+)\]/',
        '/"([^"]+)":(http[^\) \n]+)/',
        '/"([^"]+)":([^\) \?\n]+)/',
        '/@([^@]+)@/',
        '/Replying to "[^"]+":[^:]+:\n?/',
        '/Replying to \[[^\]]+\]:\n?/',
        '/[^ ]+ wrote:\n?/',
        '/#comment:[0-9]+/',
        '/---+/',
        '/\*([^\*\n]+)\*/',
        '/\n\*\* /',
        '/\n*!([^-][^!]+)!/',
        '/commit:/',
        '/www\.typolight\.org/',
        '/http:\/\/dev\.typolight\.org\/ticket\/([0-9]+)/'
    );
    protected $arrReplace = array(
        "'",
        '$1[_at_]$2',
        "\n\n```$1\n",
        "\n\n```\n",
        "\n```\n\n",
        "\n```\n\n",
        '$1',
        '[$1]($2)',
        '[$1]($2)',
        '`$1`',
        '123',
        '123',
        '123',
        '123',
        '```',
        '**$1**',
        "\n* ",
        "\n\n![]($1)",
        '',
        'www.contao.org',
        '#$1'
    );


    /* -------------------------------------------------------------------------
     * Core Part
     */
    // Vars
    protected $strPath;
    protected $arrCSV;
    protected $arrLabels;
    protected $arrIssues;
    protected $arrMilestones;

    public function Redmine2Github()
    {
        $this->strPath = getcwd();
        $this->arrLabels = array();
    }

    /**
     * Main function for export/import
     */
    public function run()
    {
        // Run CSV
        $this->checkFile();
        $this->importCSV();

        // Run issues
        $this->getAllIssues();

        // Run Labels
        $this->getAllLabels();
        $this->runUpdateLabels();

        // Run milestones
        $this->getAllMilestones();

        // Build Issue
        $this->runImportIssues();
        echo "Done";
    }

    /**
     * Create new labels, if not exists
     */
    protected function runUpdateLabels()
    {
        $arrInsert = array();

        foreach ($this->arrAcceptedLabels as $key => $value)
        {
            if (!key_exists($value["name"], $this->arrLabels) && !in_array($value["name"], $arrInsert))
            {
                if (!$this->addNewLabel($value["name"], $value["color"]))
                {
                    unset($this->arrAcceptedLabels[$key]);
                    echo "Could not add new label: " . $value["name"] . " \n<br>\n";
                }
                else
                {
                    $arrInsert[] = $value["name"];
                }
            }
        }
    }

    /**
     * Import/Update all issues from csv
     */
    protected function runImportIssues()
    {
        // Build Issue
        foreach ($this->arrCSV as $key => $value)
        {
            // Skip first one and empty entries
            if ($key == 0 || empty($value))
            {
                continue;
            }

            //  Init some vars
            $arrAdditionalContent = array();
            $strUsername = "";
            $strPassword = "";

            // User lookup
            if (key_exists($value["Autor"], $this->arrUser))
            {
                $strUsername = $this->arrUser[$value["Autor"]]["login"];
                $strPassword = $this->arrUser[$value["Autor"]]["password"];
            }
            else if (is_array($this->arrUser) && count($this->arrUser) > 0)
            {
                $arrKeys = array_keys($this->arrUser);

                $strUsername = $this->arrUser[$arrKeys[0]]["login"];
                $strPassword = $this->arrUser[$arrKeys[0]]["password"];

                $arrAdditionalContent[] = vsprintf($this->strOriginalAutor, array($value["Autor"], $value["Created"]));
            }
            else
            {
                throw new Exception("No fallback user found.");
            }

            // User lookup for assigned
            if (key_exists($value["Assigned"], $this->arrUser))
            {
                $strAssignee = $this->arrUser[$value["Assigned"]]["login"];
            }
            else
            {
                $strAssignee = null;
            }

            // Basic parameter
            $strTitle = $value["Topic"];
            $strBody = $value["Description"];

            // Build labels
            $arrLabels = array();

            if (key_exists($value["Status"], $this->arrAcceptedLabels))
            {
                $arrLabels[] = $this->arrAcceptedLabels[$value["Status"]]["name"];
            }

            if (key_exists($value["Tracker"], $this->arrAcceptedLabels))
            {
                $arrLabels[] = $this->arrAcceptedLabels[$value["Tracker"]]["name"];
            }

            $strMilestone = null;
            $intMilestone = null;

            // Check milestones
            if ($value["Target Version"] != "")
            {
                $strMilestone = vsprintf($this->strMilestoneVersion, array($value["Target Version"]));

                if (!key_exists($strMilestone, $this->arrMilestones))
                {
                    $this->addNewMilestone($strMilestone);
                }

                $intMilestone = $this->arrMilestones[$strMilestone]["number"];
            }

            // Check if we have an update or a new issue
            if (($intKey = array_search($value["Topic"], $this->arrIssues)) !== FALSE)
            {
                $this->arrCSV[$key]["updateID"] = $intKey;
                $this->arrCSV[$key]["execute"] = $this->buildCurl($strUsername, $strPassword, $strTitle, $strBody, $strAssignee, $intMilestone, $arrLabels, $arrAdditionalContent, $intKey);
            }
            else
            {
                $this->arrCSV[$key]["execute"] = $this->buildCurl($strUsername, $strPassword, $strTitle, $strBody, $strAssignee, $intMilestone, $arrLabels, $arrAdditionalContent);
            }
        }

        // Execute
        foreach ($this->arrCSV as $key => $value)
        {
            // Skip empty, first one or entries without execute parameter
            if ($key == 0 || empty($value) || $value["execute"] == "")
            {
                continue;
            }

            // Insert/Update issue
            try
            {
                $arrResponse = $this->executeProc($value["execute"]);

                if ($arrResponse["data"]["message"] == "Max number of login attempt exceeded" || $arrResponse["data"]["message"] == "Bad credentials")
                {
                    throw new Exception("Wrong user login.");
                }

                if (!isset($arrResponse["data"]["number"]))
                {
                    throw new Exception("Error by adding issue. Response from server: " . json_encode($arrResponse["data"]));
                }

                if ($value["Status"] == "Closed" || $value["Status"] == "Completed")
                {
                    $this->closeIssue($arrResponse["data"]["number"]);
                }
            }
            catch (Exception $exc)
            {
                echo" Skiped " . $value["id"] . " with message: " . $exc->getMessage() . " <br />\n";
            }
        }
    }

    /* -------------------------------------------------------------------------
     * GitHub Api Calls
     */

    /**
     * Get all labels from repo.
     */
    protected function getAllLabels()
    {
        $strCurl = "curl -i " . $this->strRepoURL . "/labels -X GET";

        $arrLables = $this->executeProc($strCurl);
        $arrLables = $arrLables["data"];

        if (!is_array($arrLables) || (key_exists("message", $arrLables) && $arrLables["message"] == "Not Found"))
        {
            throw new Exception("Could not load labels from repo.");
        }

        foreach ($arrLables as $key => $value)
        {
            $this->arrLabels[$value["name"]] = $value;
        }
    }

    /**
     * Get all milestones from repo.
     */
    protected function getAllMilestones()
    {
        $strCurl = "curl -i " . $this->strRepoURL . "/milestones -X GET";

        $arrMilestones = $this->executeProc($strCurl);
        $arrMilestones = $arrMilestones["data"];

        if (!is_array($arrMilestones) || (key_exists("message", $arrMilestones) && $arrMilestones["message"] == "Not Found"))
        {
            throw new Exception("Could not load labels from repo.");
        }

        foreach ($arrMilestones as $key => $value)
        {
            $this->arrMilestones[$value["title"]] = $value;
        }
    }

    /**
     * Get akk issues from repo.
     */
    protected function getAllIssues()
    {
        $arrIssues = array();

        // Load open issues
        for ($i = 1; $i < 100; $i++)
        {
            $strCurl = "curl -i '" . $this->strRepoURL . "/issues?state=open&page=$i'";
            $arrIssuesOpen = $this->executeProc($strCurl);

            $arrIssues = array_merge($arrIssues, $arrIssuesOpen["data"]);

            if (key_exists("Link", $arrIssuesOpen["header"]))
            {
                if (strpos($arrIssuesOpen["header"]["Link"], "rel=\"next\"") === FALSE)
                {
                    break;
                }
            }
            else
            {
                break;
            }
        }

        // Load closed issues
        for ($i = 1; $i < 100; $i++)
        {
            $strCurl = "curl -i '" . $this->strRepoURL . "/issues?page=$i&state=closed'";
            $arrIssuesClosed = $this->executeProc($strCurl);

            $arrIssues = array_merge($arrIssues, $arrIssuesClosed["data"]);

            if (key_exists("Link", $arrIssuesClosed["header"]))
            {
                if (strpos($arrIssuesClosed["header"]["Link"], "rel=\"next\"") === FALSE)
                {
                    break;
                }
            }
            else
            {
                break;
            }
        }

        if (!is_array($arrIssues) || (key_exists("message", $arrIssues) && $arrIssues["message"] == "Not Found"))
        {
            throw new Exception("Could not load issues from repo.");
        }

        foreach ($arrIssues as $key => $value)
        {
            $this->arrIssues[$value["number"]] = $value["title"];
        }
    }

    /**
     * Add a new label
     * 
     * @param string $strName
     * @param sting $strColor
     * @return boolean 
     */
    protected function addNewLabel($strName, $strColor)
    {
        $strParameter = json_encode(array("name" => $strName, "color" => $strColor));
        $strCurl = "curl -i " . $this->strRepoURL . "/labels -u \"" . $this->strRepoUser . ":" . $this->strRepoPassword . "\" -X POST -d '$strParameter'";

        $arrResponse = $this->executeProc($strCurl);

        if ($arrResponse["data"]["message"] == "Max number of login attempt exceeded" || $arrResponse["data"]["message"] == "Bad credentials")
        {
            throw new Exception("Error by login with user: $this->strRepoUser to create labels.");
        }

        $arrResponse = $arrResponse["data"];

        if ($arrResponse === FALSE)
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * Add a new milestone
     * 
     * @param string $strName
     * @param sting $strColor
     * @return boolean 
     */
    protected function addNewMilestone($strName)
    {
        $strParameter = json_encode(array("title" => $strName));
        $strCurl = "curl -i " . $this->strRepoURL . "/milestones -u \"" . $this->strRepoUser . ":" . $this->strRepoPassword . "\" -X POST -d '$strParameter'";

        $arrResponse = $this->executeProc($strCurl);

        if ($arrResponse["data"]["message"] == "Max number of login attempt exceeded" || $arrResponse["data"]["message"] == "Bad credentials")
        {
            throw new Exception("Error by login with user: $this->strRepoUser to create milestones.");
        }

        $arrResponse = $arrResponse["data"];

        $this->arrMilestones[$strName] = $arrResponse;

        if ($arrResponse === FALSE)
        {
            return false;
        }
        else
        {
            return true;
        }
    }

    /**
     * Close a issue
     * 
     * @param int $intID 
     */
    protected function closeIssue($intID)
    {
        $strParameter = json_encode(array("state" => "closed"));
        $strCurl = "curl -i " . $this->strRepoURL . "/issues/$intID -u \"" . $this->strRepoUser . ":" . $this->strRepoPassword . "\" -X POST -d '$strParameter'";

        $arrResponse = $this->executeProc($strCurl);

        if ($arrResponse["data"]["message"] == "Max number of login attempt exceeded" || $arrResponse["data"]["message"] == "Bad credentials")
        {
            throw new Exception("Could not close issue $intID, because we have a wrong user or password.");
        }
        else if (strpos($arrResponse["header"]["Status"], "200 OK") === FALSE)
        {
            throw new Exception("Could not close issue $intID, because we have erro on server side with id: " . $arrResponse["header"]["Status"]);
        }
    }

    /**
     * Build execution string for a new issue
     * 
     * @param string $strUsername
     * @param string $strUserpassword
     * @param string $strTitle
     * @param string $strBody
     * @param string $strAssignee
     * @param string $strMilestone
     * @param string $arrLabels
     * @param string $arrAdditionalContent
     * @param int $intUpdateID
     * @return string 
     */
    protected function buildCurl($strUsername, $strUserpassword, $strTitle, $strBody, $strAssignee = null, $intMilestone = null, $arrLabels = null, $arrAdditionalContent = null, $intUpdateID = false)
    {
        if (empty($strUsername) || empty($strUserpassword))
        {
            throw new Exception("Missing user login.");
        }

        if (empty($strTitle))
        {
            throw new Exception("Missing issue title.");
        }

        $arrParamteter = array();

        // Build body
        if (!empty($arrAdditionalContent) && is_array($arrAdditionalContent) && count($arrAdditionalContent) > 0)
        {
            $arrParamteter["body"] = $strBody;
            $arrParamteter["body"] .= "\n\n";

            foreach ($arrAdditionalContent as $key => $value)
            {
                $arrParamteter["body"] .= $value . "\n";
            }
        }
        else
        {
            $arrParamteter["body"] = $strBody;
        }

        $arrParamteter["title"] = $strTitle;

        // Check assignee
        if (!empty($strAssignee))
        {
            $arrParamteter["assignee"] = $strAssignee;
        }

        // Check milestone
        if ($intMilestone != null)
        {
            $arrParamteter["milestone"] = $intMilestone;
        }

        // Check and build lables
        if (!empty($arrLabels) && is_array($arrLabels) && count($arrLabels) != 0)
        {
            $arrParamteter["labels"] = $arrLabels;
        }

        if ($intUpdateID == FALSE)
        {
            $strParameter = $this->escape(json_encode($arrParamteter));
            return "curl -i " . $this->strRepoURL . "/issues -u \"" . $strUsername . ":" . $strUserpassword . "\" -X POST -d '$strParameter'";
        }
        else
        {
            $strParameter = $this->escape(json_encode($arrParamteter));
            return "curl -i " . $this->strRepoURL . "/issues/$intUpdateID -u \"$strUsername:$strUserpassword\" -X POST -d '$strParameter'";
        }
    }

    /* -------------------------------------------------------------------------
     * Helper Part
     */

    /**
     * Check if file exists
     */
    protected function checkFile()
    {
        if (!file_exists($this->strPath . "/" . $this->strCSVPath))
        {
            throw new Exception("Missing CSV file");
        }
    }

    /**
     * Formate funktion 
     * 
     * @param String $str
     * @return String 
     */
    protected function format($str)
    {
        $str = str_replace("\r", '', $str);
        $str = preg_replace($this->arrSearch, $this->arrReplace, $str);
        $str = str_replace('[_at_]', '@', $str);
        return trim($str);
    }

    /**
     * Import CSV File
     */
    protected function importCSV()
    {
        // Read CSV and formate it
        $objFH = fopen($this->strPath . "/" . $this->strCSVPath, "r+");

        $strFileBody = "";

        while (($strRow = fgets($objFH)) !== FALSE)
        {
            $strFileBody .= $strRow;
        }

        fclose($objFH);

        $strFileBody = str_replace(array("Ã–", "Ã„", "Ãœ", "Ã¶", "Ã¤", "Ã¼"), array("##Ã–", "##Ã„", "##Ãœ", "##Ã¶", "##Ã¤", "##Ã¼"), $strFileBody);

        // Write Temp file
        $objTFH = tmpfile();
        fputs($objTFH, $strFileBody, strlen($strFileBody));
        rewind($objTFH);

        // Convert to array
        while (($arrRow = fgetcsv($objTFH, 0, $this->strCSVDelimiter)) !== FALSE)
        {
            $this->arrCSV[] = array_combine($this->arrCSVKeys, $arrRow);
        }

        // Formate some Strings
        foreach ($this->arrCSV as $key => $value)
        {
            foreach ($value as $keyField => $valueField)
            {
                $valueField = str_replace(array("##Ã–", "##Ã„", "##Ãœ", "##Ã¶", "##Ã¤", "##Ã¼"), array("Ã–", "Ã„", "Ãœ", "Ã¶", "Ã¤", "Ã¼"), $valueField);

                switch ($keyField)
                {
                    case "Description":
                        $valueField = $this->format($valueField);
                        break;
                }

                $this->arrCSV[$key][$keyField] = $valueField;
            }
        }
    }

    /**
     * Execute external program
     * Big thanks to Tristan Lins <tristan.lins@infinitysoft.de> for the inspiration
     *
     * @arg mixed...
     * @return boolean
     */
    protected function executeProc($strExecute)
    {
        // execute the command
        $proc = proc_open(
                $strExecute, array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
                ), $arrPipes);

        // test if command start failed
        if ($proc === false)
        {
            throw new Exception("Could not execute command: '$strExecute' ");
        }

        // close stdin
        fclose($arrPipes[0]);

        // read and close stdout
        $strOut = stream_get_contents($arrPipes[1]);
        fclose($arrPipes[1]);

        // read and close stderr
        $strErr = stream_get_contents($arrPipes[2]);
        fclose($arrPipes[2]);

        // wait until process terminates
        $intCode = proc_close($proc);

        // log if process does not terminate without errors
        if ($intCode != 0)
        {
            throw new Exception("Program execution failed command: $strExecute <br/>\n  stdout: $strOut  <br/>\n stderr: $strErr");
        }

        $mixResponse = explode("\n", $strOut);
        $arrHeader = array();

        foreach ($mixResponse as $key => $value)
        {
            if (strpos($value, "HTTP/1.1") === FALSE)
            {
                // Save Header
                $arrHeaderPair = explode(":", $value, 2);
                $arrHeader[$arrHeaderPair[0]] = $arrHeaderPair[1];
            }

            // Unset
            unset($mixResponse[$key]);

            // Break if we have reached the last header field
            if (strpos($value, "Content-Length") !== FALSE)
            {
                break;
            }
        }

        $mixResponse = json_decode(trim(implode("", $mixResponse)), true);

        if (strpos($arrHeader["Status"], "200 OK") === FALSE && strpos($arrHeader["Status"], "201 Created") === FALSE)
        {
            //echo "Execution command: $strExecute <br/>\n  stdout: $strOut  <br/>\n stderr: $strErr";           

            throw new Exception("We have an error on server side with id: " . $arrHeader["Status"] . "\n<br />\n");
        }

        return array("data" => $mixResponse, "header" => $arrHeader);
    }

    protected function escape($string)
    {
        return str_replace(array("(", ")", "'", "?", "`"), array("\\u0028", "\\u0029", "\\u0027", "\\u00DF", "\\u0060"), $string);
    }

}

try
{
    $Redmine2Github = new Redmine2Github();
    $Redmine2Github->run();
}
catch (Exception $exc)
{
    echo "Sorry we have an error:  ";
    echo $exc->getMessage();
}
?>