<?php
class Note extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::handleLogin();
    }

    public function index()
    {
        $note_model = $this->loadModel('Note');
        $this->view->notes = $note_model->getAllNotes();
        $this->view->render('note/index');
    }

    public function create()
    {
        if (isset($_POST['note_text']) AND !empty($_POST['note_text'])) {
            $note_model = $this->loadModel('Note');
            $note_model->create($_POST['note_text']);
        }
        header('location: ' . URL . 'note');
    }
    public function edit($note_id)
    {
        if (isset($note_id)) {
            $note_model = $this->loadModel('Note');
            $this->view->note = $note_model->getNote($note_id);
            $this->view->render('note/edit');
        } else {
            header('location: ' . URL . 'note');
        }
    }
    public function editSave($note_id)
    {
        if (isset($_POST['note_text']) && isset($note_id)) {
            $note_model = $this->loadModel('Note');
            $note_model->editSave($note_id, $_POST['note_text']);
        }
        header('location: ' . URL . 'note');
    }
    public function delete($note_id)
    {
        if (isset($note_id)) {
            $note_model = $this->loadModel('Note');
            $note_model->delete($note_id);
        }
        header('location: ' . URL . 'note');
    }
}
