'use strict';

const apiUrl = 'feedback.php';
const statusBox = document.querySelector('#feedback-status');
const commentForm = document.querySelector('#comment-form');
const commentInput = document.querySelector('#comment-input');
const commentList = document.querySelector('#comment-list');
const ratingForm = document.querySelector('#rating-form');
const clearRatingButton = document.querySelector('#clear-rating');
const characterCount = document.querySelector('#character-count');

let csrfToken = '';

function showStatus(message, isError = false) {
  statusBox.textContent = message;
  statusBox.classList.toggle('error', isError);
}

async function requestFeedback(payload = null) {
  const options = payload
    ? {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...payload, csrf: csrfToken }),
      }
    : { method: 'GET' };

  const response = await fetch(apiUrl, options);
  const result = await response.json();
  if (!response.ok || !result.ok) {
    throw new Error(result.message || 'Feedback could not be loaded.');
  }
  return result;
}

function makeActionButton(label, action, commentId) {
  const button = document.createElement('button');
  button.type = 'button';
  button.textContent = label;
  button.addEventListener('click', () => {
    if (action === 'edit') {
      showCommentEditor(commentId, button.closest('.comment-item'));
    } else if (window.confirm('Delete this comment?')) {
      submitAction({ action: 'delete_comment', comment_id: commentId });
    }
  });
  return button;
}

function showCommentEditor(commentId, row) {
  const existing = row.querySelector('.comment-text').textContent;
  const form = document.createElement('form');
  form.className = 'comment-edit';

  const field = document.createElement('textarea');
  field.name = 'comment';
  field.maxLength = 300;
  field.required = true;
  field.value = existing;

  const save = document.createElement('button');
  save.className = 'button';
  save.type = 'submit';
  save.textContent = 'Save edit';

  form.append(field, save);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    await submitAction({
      action: 'edit_comment',
      comment_id: commentId,
      comment: field.value.trim(),
    });
  });
  row.append(form);
  field.focus();
}

function renderComments(comments) {
  commentList.replaceChildren();

  comments.forEach((comment) => {
    const row = document.createElement('li');
    row.className = 'comment-item';

    const text = document.createElement('p');
    text.className = 'comment-text';
    text.textContent = comment.comment;

    const meta = document.createElement('div');
    meta.className = 'comment-meta';

    const date = document.createElement('time');
    date.dateTime = comment.created_at;
    date.textContent = new Date(`${comment.created_at.replace(' ', 'T')}Z`).toLocaleDateString();
    meta.append(date);

    if (comment.is_mine) {
      const actions = document.createElement('div');
      actions.className = 'comment-actions';
      actions.append(
        makeActionButton('Edit', 'edit', comment.id),
        makeActionButton('Delete', 'delete', comment.id),
      );
      meta.append(actions);
    }

    row.append(text, meta);
    commentList.append(row);
  });
}

function renderRating(rating) {
  const chosen = rating ? String(rating) : '';
  ratingForm.querySelectorAll('input[name="rating"]').forEach((input) => {
    input.checked = input.value === chosen;
    input.closest('label').classList.toggle('is-selected', Number(input.value) <= Number(chosen));
  });
  clearRatingButton.hidden = !rating;
}

async function loadFeedback() {
  try {
    const data = await requestFeedback();
    csrfToken = data.csrf;
    renderRating(data.rating);
    renderComments(data.comments);
    showStatus('Feedback is stored in the shared database.');
  } catch (error) {
    showStatus(`${error.message} Start PHP and MySQL, then open this page through its local server.`, true);
  }
}

async function submitAction(payload) {
  try {
    const result = await requestFeedback(payload);
    csrfToken = result.csrf || csrfToken;
    renderRating(result.rating);
    renderComments(result.comments);
    if (payload.action === 'add_comment') {
      commentForm.reset();
      updateCharacterCount();
    }
    showStatus(result.message || 'Feedback updated.');
  } catch (error) {
    showStatus(error.message, true);
  }
}

function updateCharacterCount() {
  characterCount.textContent = `${commentInput.value.length} / 300`;
}

ratingForm.addEventListener('submit', (event) => {
  event.preventDefault();
  const rating = new FormData(ratingForm).get('rating');
  if (!rating) {
    showStatus('Choose a star rating first.', true);
    return;
  }
  submitAction({ action: 'rate', rating: Number(rating) });
});

clearRatingButton.addEventListener('click', () => {
  submitAction({ action: 'clear_rating' });
});

commentInput.addEventListener('input', updateCharacterCount);
commentForm.addEventListener('submit', (event) => {
  event.preventDefault();
  const comment = commentInput.value.trim();
  if (!comment) {
    showStatus('Write a comment before posting.', true);
    commentInput.focus();
    return;
  }
  submitAction({ action: 'add_comment', comment });
});

loadFeedback();
