<?php
class NoteModel
{
    public function __construct(Database $db)
    {
        $this->db = $db;
    }
    public function getAllNotes()
    {
        $sql = "SELECT user_id, note_id, note_text FROM notes WHERE user_id = :user_id";
        $query = $this->db->prepare($sql);
        $query->execute(array(':user_id' => $_SESSION['user_id']));
        return $query->fetchAll();
    }
    public function getNote($note_id)
    {
        $sql = "SELECT user_id, note_id, note_text FROM notes WHERE user_id = :user_id AND note_id = :note_id";
        $query = $this->db->prepare($sql);
        $query->execute(array(':user_id' => $_SESSION['user_id'], ':note_id' => $note_id));
        return $query->fetch();
    }
    public function create($note_text)
    {
        $note_text = strip_tags($note_text);

        $sql = "INSERT INTO notes (note_text, user_id) VALUES (:note_text, :user_id)";
        $query = $this->db->prepare($sql);
        $query->execute(array(':note_text' => $note_text, ':user_id' => $_SESSION['user_id']));

        $count =  $query->rowCount();
        if ($count == 1) {
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_NOTE_CREATION_FAILED;
        }
        return false;
    }
    public function editSave($note_id, $note_text)
    {
        $note_text = strip_tags($note_text);

        $sql = "UPDATE notes SET note_text = :note_text WHERE note_id = :note_id AND user_id = :user_id";
        $query = $this->db->prepare($sql);
        $query->execute(array(':note_id' => $note_id, ':note_text' => $note_text, ':user_id' => $_SESSION['user_id']));

        $count =  $query->rowCount();
        if ($count == 1) {
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_NOTE_EDITING_FAILED;
        }
        return false;
    }
    public function delete($note_id)
    {
        $sql = "DELETE FROM notes WHERE note_id = :note_id AND user_id = :user_id";
        $query = $this->db->prepare($sql);
        $query->execute(array(':note_id' => $note_id, ':user_id' => $_SESSION['user_id']));

        $count =  $query->rowCount();

        if ($count == 1) {
            return true;
        } else {
            $_SESSION["feedback_negative"][] = FEEDBACK_NOTE_DELETION_FAILED;
        }
        return false;
    }
}
